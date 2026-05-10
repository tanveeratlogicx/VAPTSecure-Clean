import json
import os
import re

def verify_integrity():
    paths = {
        'schema': 'data/interface_schema_v2.0.json',
        'library': 'data/enforcer_pattern_library_v2.0.json',
        'manifest': 'data/vapt_driver_manifest_v2.0.json'
    }
    
    data = {}
    for key, path in paths.items():
        if not os.path.exists(path):
            print(f"ERROR: Missing file {path}")
            return
        with open(path, 'r', encoding='utf-8') as f:
            data[key] = json.load(f)

    # 1. Total Risk Count Verification
    risks_schema = data['schema'].get('risk_interfaces', {})
    risks_lib = data['library'].get('patterns', {})
    risks_manifest = data['manifest'].get('risks', {})
    
    print(f"--- Total Risk Count ---")
    print(f"Schema Risks: {len(risks_schema)}")
    print(f"Library Risks: {len(risks_lib)}")
    print(f"Manifest Risks: {len(risks_manifest)}")
    
    issues = []

    # 2. Cross-File Presence Check
    all_ids = set(risks_schema.keys()) | set(risks_lib.keys()) | set(risks_manifest.keys())
    
    print("\n--- Cross-File Consistency ---")
    for rid in sorted(all_ids):
        if rid not in risks_schema: issues.append(f"[{rid}] Missing in Schema")
        if rid not in risks_lib: issues.append(f"[{rid}] Missing in Pattern Library")
        if rid not in risks_manifest: issues.append(f"[{rid}] Missing in Manifest")

    # 3. Platform & Implementation Consistency
    print("--- Platform Tier Alignment ---")
    forbidden = ['iis', 'caddy']
    
    for rid, rschema in risks_schema.items():
        platforms = rschema.get('available_platforms', [])
        impls = rschema.get('platform_implementations', {})
        
        # Check for forbidden platforms
        for p in platforms:
            if p.lower() in forbidden:
                issues.append(f"[{rid}] Forbidden platform found: {p}")
        
        # Check if each platform has implementation in schema
        for p in platforms:
            if p not in impls:
                issues.append(f"[{rid}] Platform {p} listed but missing in implementation block")
                continue
                
            # Check implementation cross-links
            code_ref = impls[p].get('code_ref', '')
            driver_ref = impls[p].get('driver_ref', '')
            
            # Verify Pattern Library Link
            if rid in risks_lib:
                lib_entry = risks_lib[rid]
                # Map platform name to lib key
                lib_key_map = {
                    'Apache': 'apache',
                    '.htaccess': 'htaccess',
                    'Nginx': 'nginx',
                    'Cloudflare': 'cloudflare',
                    'PHP Functions': 'php_functions',
                    'wp-config.php': 'wp_config'
                }
                lk = lib_key_map.get(p)
                if lk and lk not in lib_entry:
                    issues.append(f"[{rid}] Platform {p} implementation missing in Pattern Library")
            
            # Verify Manifest Link
            if rid in risks_manifest:
                steps = risks_manifest[rid].get('steps', [])
                if not any(s.get('enforcer') == p for s in steps):
                    issues.append(f"[{rid}] Platform {p} implementation missing in Driver Manifest")

    # 4. Text Cleanup Verification (IIS/Caddy residue)
    print("--- Residue Check (IIS/Caddy) ---")
    for key, path in paths.items():
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read().lower()
            if 'iis' in content or 'caddy' in content:
                # Use regex to find if it's just part of a word or an actual entry
                if re.search(r'\b(iis|caddy)\b', content):
                    issues.append(f"Residue found in {path}: Possible IIS/Caddy mentions")

    # Final Output
    print("\n--- Summary ---")
    if not issues:
        print("✅ SUCCESS: Data files are synchronized and clean of drift.")
    else:
        print(f"❌ FAILED: {len(issues)} drift issues found.")
        for issue in issues:
            print(f"  - {issue}")

if __name__ == "__main__":
    verify_integrity()
