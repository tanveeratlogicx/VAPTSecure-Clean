import json
import os

def align_batch_4():
    paths = {
        'schema': 'data/interface_schema_v2.0.json',
        'library': 'data/enforcer_pattern_library_v2.0.json',
        'manifest': 'data/vapt_driver_manifest_v2.0.json'
    }
    
    data = {}
    for key, path in paths.items():
        with open(path, 'r', encoding='utf-8') as f:
            data[key] = json.load(f)

    # Batch 4: RISK-091 to RISK-120
    batch_ids = [f"RISK-{str(i).zfill(3)}" for i in range(91, 121)]
    
    for risk_id in batch_ids:
        if risk_id not in data['schema']['risk_interfaces']:
            continue
            
        risk_schema = data['schema']['risk_interfaces'][risk_id]
        risk_lib = data['library']['patterns'].get(risk_id, {})
        risk_manifest = data['manifest']['risks'].get(risk_id, {'steps': []})
        title = risk_schema.get('title', '')
        target_path = risk_id # Default

        # --- NGINX ALIGNMENT ---
        if "Nginx" not in risk_schema['available_platforms'] and ".htaccess" in risk_schema['available_platforms']:
            risk_schema['available_platforms'].append("Nginx")
            target_path = risk_id
            nginx_code = f"location ~* {target_path} {{\n    deny all;\n}}"
            
            risk_schema['platform_implementations']['Nginx'] = {
                "lib_key": "nginx",
                "code_ref": f"enforcer_pattern_library_v2.0.patterns.{risk_id}.nginx",
                "driver_ref": f"vapt_driver_manifest_v2.0.risks.{risk_id}.steps[*enforcer=Nginx]",
                "operation": "add_location_block",
                "target_file": "Nginx",
                "backup_required": True,
                "implementation_type": "nginx_location"
            }

            risk_lib['nginx'] = {
                "enforcer": "Nginx",
                "operation": "add_location_block",
                "target_file": "/etc/nginx/conf.d/vapt-security.conf",
                "insertion_point": "http_block",
                "anchor": {"search": None, "position": "after", "fallback": "append"},
                "code": nginx_code,
                "wrapped_code": f"# BEGIN VAPT {risk_id}\n{nginx_code}\n# END VAPT {risk_id}",
                "begin_marker": f"# BEGIN VAPT {risk_id}",
                "end_marker": f"# END VAPT {risk_id}",
                "verification": {"command": "nginx -t", "expected": "No errors"},
                "driver": {
                    "write_mode": "insert_at_anchor",
                    "target_file": "/etc/nginx/conf.d/vapt-security.conf",
                    "write_block": f"# BEGIN VAPT {risk_id}\n{nginx_code}\n# END VAPT {risk_id}",
                    "anchor_string": "http {",
                    "anchor_position": "after",
                    "idempotency_check": f"# BEGIN VAPT {risk_id}",
                    "if_found": "skip"
                }
            }
            
            if not any(s.get('enforcer') == 'Nginx' for s in risk_manifest['steps']):
                risk_manifest['steps'].append({
                    "enforcer": "Nginx",
                    "lib_key": "nginx",
                    "operation": "add_location_block",
                    "write_mode": "insert_at_anchor",
                    "target_file": "/etc/nginx/conf.d/vapt-security.conf",
                    "backup_required": True,
                    "idempotency": {"check_string": f"# BEGIN VAPT {risk_id}", "if_found": "skip", "if_not_found": "insert"},
                    "insertion": {"anchor_string": "http {", "anchor_position": "after", "fallback": "append"},
                    "write_block": f"# BEGIN VAPT {risk_id}\n{nginx_code}\n# END VAPT {risk_id}",
                    "begin_marker": f"# BEGIN VAPT {risk_id}",
                    "end_marker": f"# END VAPT {risk_id}",
                    "verification": {"command": "nginx -t", "expected": "No errors"}
                })

        # --- PHP FUNCTIONS ALIGNMENT ---
        if "PHP Functions" not in risk_schema['available_platforms'] and "wp-config.php" not in risk_schema['available_platforms']:
            risk_schema['available_platforms'].append("PHP Functions")
            risk_schema['platform_implementations']['PHP Functions'] = {
                "lib_key": "php_functions",
                "code_ref": f"enforcer_pattern_library_v2.0.patterns.{risk_id}.php_functions",
                "driver_ref": f"vapt_driver_manifest_v2.0.risks.{risk_id}.steps[*enforcer=PHP Functions]",
                "operation": "add_action_hook",
                "target_file": "PHP Functions",
                "backup_required": True,
                "implementation_type": "wp_hook"
            }
            
            hook_name = "init"
            php_logic = f"if (strpos($_SERVER['REQUEST_URI'], '{target_path}') !== false) {{ wp_die('Access Denied'); }}"
            php_code = f"add_action('{hook_name}', function() {{\n    {php_logic}\n}});"
            
            risk_lib['php_functions'] = {
                "enforcer": "PHP Functions",
                "operation": "add_action_hook",
                "target_file": "PHP Functions",
                "insertion_point": "functions_php",
                "anchor": {"search": None, "position": "append", "fallback": None},
                "code": php_code,
                "wrapped_code": f"// BEGIN VAPT {risk_id}\n{php_code}\n// END VAPT {risk_id}",
                "begin_marker": f"// BEGIN VAPT {risk_id}",
                "end_marker": f"// END VAPT {risk_id}",
                "verification": {"command": f"wp eval \"do_action('{hook_name}');\"", "expected": "No errors"},
                "driver": {
                    "write_mode": "append_to_file",
                    "target_file": "{ABSPATH}wp-content/plugins/vapt-protection-suite/vapt-functions.php",
                    "write_block": f"// BEGIN VAPT {risk_id}\n{php_code}\n// END VAPT {risk_id}",
                    "anchor_string": None,
                    "anchor_position": "append",
                    "idempotency_check": f"// BEGIN VAPT {risk_id}",
                    "if_found": "skip"
                }
            }
            
            if not any(s.get('enforcer') == 'PHP Functions' for s in risk_manifest['steps']):
                risk_manifest['steps'].append({
                    "enforcer": "PHP Functions",
                    "lib_key": "php_functions",
                    "operation": "add_action_hook",
                    "write_mode": "append_to_file",
                    "target_file": "{ABSPATH}wp-content/plugins/vapt-protection-suite/vapt-functions.php",
                    "backup_required": True,
                    "idempotency": {"check_string": f"// BEGIN VAPT {risk_id}", "if_found": "skip", "if_not_found": "insert"},
                    "insertion": {"anchor_string": None, "anchor_position": "append", "fallback": None},
                    "write_block": f"// BEGIN VAPT {risk_id}\n{php_code}\n// END VAPT {risk_id}",
                    "begin_marker": f"// BEGIN VAPT {risk_id}",
                    "end_marker": f"// END VAPT {risk_id}",
                    "verification": {"command": f"wp eval \"do_action('{hook_name}');\"", "expected": "No errors"}
                })

        data['manifest']['risks'][risk_id] = risk_manifest

    for key, path in paths.items():
        with open(path, 'w', encoding='utf-8') as f:
            json.dump(data[key], f, indent=2)

if __name__ == "__main__":
    align_batch_4()
    print("Batch 4 aligned successfully.")
