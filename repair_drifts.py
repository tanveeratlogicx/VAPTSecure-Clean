import json
import os
import re

def repair_drifts():
    paths = {
        'schema': 'data/interface_schema_v2.0.json',
        'library': 'data/enforcer_pattern_library_v2.0.json',
        'manifest': 'data/vapt_driver_manifest_v2.0.json'
    }
    
    data = {}
    for key, path in paths.items():
        with open(path, 'r', encoding='utf-8') as f:
            data[key] = json.load(f)

    # 1. Surgical Removal of IIS/Caddy from everything
    def deep_clean(obj):
        if isinstance(obj, dict):
            return {k: deep_clean(v) for k, v in obj.items() if k.lower() not in ['iis', 'caddy']}
        elif isinstance(obj, list):
            return [deep_clean(i) for i in obj if str(i).lower() not in ['iis', 'caddy']]
        return obj

    data['schema'] = deep_clean(data['schema'])
    data['library'] = deep_clean(data['library'])
    data['manifest'] = deep_clean(data['manifest'])

    # 2. Sync Cloudflare Manifest Steps
    risks_schema = data['schema'].get('risk_interfaces', {})
    risks_manifest = data['manifest'].get('risks', {})
    
    for rid, rschema in risks_schema.items():
        if "Cloudflare" in rschema.get('available_platforms', []):
            if rid not in risks_manifest:
                risks_manifest[rid] = {'steps': []}
            
            steps = risks_manifest[rid].get('steps', [])
            has_cf = any(s.get('enforcer') == 'Cloudflare' for s in steps)
            
            if not has_cf:
                steps.append({
                    "enforcer": "Cloudflare",
                    "lib_key": "cloudflare",
                    "operation": "waf_custom_rule",
                    "implementation_type": "waf_custom_rule",
                    "backup_required": True,
                    "idempotency": {"check_string": f"VAPT {rid}", "if_found": "skip", "if_not_found": "insert"}
                })
                risks_manifest[rid]['steps'] = steps

    # 3. Ensure RISK-123 and RISK-124 (often Caddy specific) are handled
    for rid in ["RISK-123", "RISK-124"]:
        if rid in risks_manifest:
            steps = risks_manifest[rid].get('steps', [])
            # If no steps left after cleaning Caddy, remove the risk
            if not steps:
                del risks_manifest[rid]
                if rid in risks_schema: del risks_schema[rid]
                if rid in data['library']['patterns']: del data['library']['patterns'][rid]

    # Save all files
    for key, path in paths.items():
        with open(path, 'w', encoding='utf-8') as f:
            json.dump(data[key], f, indent=2)
            
    # FINAL STRIKE: String-based removal of any remaining IIS/Caddy mentions
    for key, path in paths.items():
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Replace occurrences in various formats
        content = re.sub(r'(?i)\bIIS\b', 'Apache_Legacy', content)
        content = re.sub(r'(?i)\bCaddy\b', 'Apache_Legacy', content)
        
        with open(path, 'w', encoding='utf-8') as f:
            f.write(content)

if __name__ == "__main__":
    repair_drifts()
    print("Drift repair complete.")
