Symlink Registry (cross-referenced with README.md)
The original header only listed Cursor and Gemini. The new version adds a full table covering all 7 editors — Claude Code, Qoder, Trae, Windsurf, and VS Code — exactly matching the paths defined in your README.md. A complete directory tree is also included so the symlink structure is self-documenting.
{domain} Placeholder + FQDN Clickable Links
Every hardcoded domain (yoursite.com, example.com, bare paths like /wp-admin/) has been replaced with https://{domain}/.... All external documentation references in the Resources table are now fully qualified, clickable https:// links. The PHP runtime replacement pattern (wp_parse_url + str_replace) is shown explicitly.
WordPress-Specific Security Exceptions
The whitelist table was expanded to cover /wp-json/oembed/1.0/ and the correct admin-ajax path (/wp-admin/admin-ajax.php, not the old root-level path). A new check_wordpress_whitelist_rules() self-check was added that scans every VAPT block for the mandatory whitelist conditions and auto-corrects if any are missing.
Self-Check Automation Enhancements

Added htaccess_modify now also fires check_wordpress_whitelist_rules() in addition to the existing checks
feature_enable / feature_disable now re-runs check_wordpress_endpoints() after the state change
All hook registrations are shown together in one place for clarity

.htaccess / Config Code Block Registry
This is the entirely new section you requested. It contains: the blank-line format contract with a visual diagram, six ready-to-use named blocks (Bot Protection, REST API Guard, XML-RPC Block, Admin AJAX Guard, File Protection, wp-includes Hardening, Security Headers), and PHP helper functions vapt_htaccess_write() and vapt_htaccess_remove() that enforce the blank-line rule and trigger self-check automatically on every operation.
Blank Line Enforcement
The check_rule_block_format() function was tightened — it now also catches multiple consecutive blank lines inside a block (not just missing ones), and the auto-corrector uses rtrim + "\n\n" to normalise to exactly one blank line every time.
