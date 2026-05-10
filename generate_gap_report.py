import json
import os

def analyze_gaps():
    schema_path = 'data/interface_schema_v2.0.json'
    if not os.path.exists(schema_path):
        return "Schema file not found."

    with open(schema_path, 'r', encoding='utf-8') as f:
        schema = json.load(f)

    report_lines = [
        "# Universal Enforcement Alignment Gap Report",
        "",
        "This report identifies missing platform implementations across the 135 risks.",
        "",
        "| Risk ID | Title | Platforms Present | Missing Key Platforms |",
        "| :--- | :--- | :--- | :--- |"
    ]

    target_platforms = [".htaccess", "Nginx", "wp-config.php", "PHP Functions", "Cloudflare", "IIS", "Caddy"]
    
    # Define which platforms are "logical" for different types of risks
    # This is a simplification, but helps in identifying major omissions.
    
    risks = schema.get('risk_interfaces', {})
    for risk_id, risk_data in sorted(risks.items()):
        if not risk_id.startswith('RISK-'):
            continue
            
        present = risk_data.get('available_platforms', [])
        title = risk_data.get('title', 'Unknown')
        
        missing = []
        # General logic for missing platforms:
        # 1. Network level risks should have .htaccess, Nginx, Cloudflare, PHP Functions, IIS, Caddy
        # 2. Policy/Bootloader risks should have wp-config.php
        
        # Check for Nginx/IIS/Caddy (High Priority for environment coverage)
        if ".htaccess" in present or "PHP Functions" in present:
            if "Nginx" not in present: missing.append("Nginx")
            if "IIS" not in present: missing.append("IIS")
            if "Caddy" not in present: missing.append("Caddy")
            
        # Check for PHP Functions (Universal Fallback)
        if "PHP Functions" not in present and not title.lower().startswith('disable') and "wp-config.php" not in present:
            missing.append("PHP Functions")
            
        # Check for wp-config.php (Policy risks)
        if "wp-config.php" not in present and ("constant" in str(risk_data).lower() or "define" in str(risk_data).lower()):
            missing.append("wp-config.php")

        report_lines.append(f"| {risk_id} | {title} | {', '.join(present)} | {', '.join(missing)} |")

    return "\n".join(report_lines)

if __name__ == "__main__":
    report_content = analyze_gaps()
    with open('Reports/Universal-Enforcement-Alignment-Report.md', 'w', encoding='utf-8') as f:
        f.write(report_content)
    print("Report generated successfully.")
