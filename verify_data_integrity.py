import json
import os
import re

def verify_integrity():
    paths = {
        'schema': 'data/interface_schema_v2.0.json',
        'library': 'data/enforcer_pattern_library_v2.0.json',
        'manifest': 'data/vapt_driver_manifest_v2.0.json',
        'instructions': 'data/ai_agent_instructions_v2.0.json',
        'readme': 'data/VAPT_AI_Agent_System_README_v2.0.md'
    }
    
    data = {}
    for key, path in paths.items():
        if not os.path.exists(path):
            print(f"ERROR: Missing file {path}")
            return
        if path.endswith('.json'):
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

    # 2. Cross-File Consistency Check
    all_ids = set(risks_schema.keys()) | set(risks_lib.keys()) | set(risks_manifest.keys())
    
    print("\n--- Cross-File Consistency ---")
    for rid in sorted(all_ids):
        if rid not in risks_schema: issues.append(f"[{rid}] Missing in Schema")
        if rid not in risks_lib: issues.append(f"[{rid}] Missing in Pattern Library")
        if rid not in risks_manifest: issues.append(f"[{rid}] Missing in Manifest")

    # 3. Platform & Implementation Consistency
    print("--- Platform Tier Alignment ---")
    # All platforms are now allowed in v2.0
    
    for rid, rschema in risks_schema.items():
        platforms = rschema.get('available_platforms', [])
        impls = rschema.get('platform_implementations', {})
        
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
                # Map platform name to lib key (v2.0 aligned)
                lib_key_map = {
                    'Apache': 'apache',
                    '.htaccess': 'htaccess',
                    'Nginx': 'nginx',
                    'Cloudflare': 'cloudflare',
                    'PHP Functions': 'php_functions',
                    'wp-config.php': 'wp_config',
                    'Server Cron': 'server_cron'
                }
                lk = lib_key_map.get(p)
                if lk and lk not in lib_entry:
                    issues.append(f"[{rid}] Platform {p} implementation missing in Pattern Library (expected lib_key: {lk})")
            
            # Verify Manifest Link
            if rid in risks_manifest:
                steps = risks_manifest[rid].get('steps', [])
                if not any(s.get('enforcer') == p for s in steps):
                    issues.append(f"[{rid}] Platform {p} implementation missing in Driver Manifest")

    # 4. Cron Verification Path Check (v3.13.8 requirement)
    print("--- Cron Verification Alignment ---")
    for rid, rschema in risks_schema.items():
        if "cron" in rschema.get('title', '').lower() or rid == 'RISK-001':
            # Check if verification path exists and is correct
            # In v2.0, verification might be in patterns or manifest
            if rid in risks_lib:
                for plat, pdata in risks_lib[rid].items():
                    if isinstance(pdata, dict) and 'verification' in pdata:
                        vcmd = pdata['verification'].get('command', '')
                        if 'wp-cron.php' in vcmd and 'doing_wp_cron' not in vcmd:
                            issues.append(f"[{rid}] {plat} verification command missing ?doing_wp_cron=1")

    # 5. Final Output
    print("\n--- Summary ---")
    if not issues:
        print("✅ SUCCESS: Data files are synchronized and clean of drift.")
    else:
        print(f"❌ FAILED: {len(issues)} drift issues found.")
        for issue in issues:
            print(f"  - {issue}")

if __name__ == "__main__":
    verify_integrity()
