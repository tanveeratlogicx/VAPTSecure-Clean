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

    # 1. Sync Cloudflare Manifest Steps
    risks_schema = data['schema'].get('risk_interfaces', {})
    risks_manifest = data['manifest'].get('risks', {})
    
    platforms_to_sync = {
        "Cloudflare": {"lib_key": "cloudflare", "operation": "waf_custom_rule", "type": "waf_custom_rule"}
    }
    
    for rid, rschema in risks_schema.items():
        available = rschema.get('available_platforms', [])
        for p, config in platforms_to_sync.items():
            if p in available:
                if rid not in risks_manifest:
                    risks_manifest[rid] = {'steps': []}
                
                steps = risks_manifest[rid].get('steps', [])
                has_platform = any(s.get('enforcer') == p for s in steps)
                
                if not has_platform:
                    steps.append({
                        "enforcer": p,
                        "lib_key": config['lib_key'],
                        "operation": config['operation'],
                        "implementation_type": config['type'],
                        "backup_required": True,
                        "idempotency": {"check_string": f"VAPT {rid}", "if_found": "skip", "if_not_found": "insert"}
                    })
                    risks_manifest[rid]['steps'] = steps

    # 2. Correct Cron Verification Paths (v3.13.8)
    for rid, rmanifest in risks_manifest.items():
        if "cron" in rid.lower() or rid == 'RISK-001':
            for step in rmanifest.get('steps', []):
                if 'verification' in step and 'wp-cron.php' in step['verification'].get('command', ''):
                    if 'doing_wp_cron' not in step['verification']['command']:
                        step['verification']['command'] = step['verification']['command'].replace('wp-cron.php', 'wp-cron.php?doing_wp_cron=1')

    # Save all files
    for key, path in paths.items():
        with open(path, 'w', encoding='utf-8') as f:
            json.dump(data[key], f, indent=2)
            
    print("Drift repair complete (v2.0 SSoT aligned).")


if __name__ == "__main__":
    repair_drifts()
    print("Drift repair complete.")
