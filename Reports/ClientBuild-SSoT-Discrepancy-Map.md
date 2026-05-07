## Client Build vs Master SSoT Discrepancy Map

This report captures the current mismatch between the master plugin implementation, the SSoT catalogs, and the generated client build. It is intended for later review and as the baseline for follow-up fixes.

### What Is Happening

The master plugin validates released features correctly, but the generated client build fails to validate the same released features.

That points to a contract drift across three layers:

- the SSoT catalog data
- the master runtime implementation
- the client build verification path

### Discrepancy Areas

1. Platform canonicalization is incomplete
- The catalog uses platform names such as `fail2ban`, `wordpress_core`, `server_cron`, `php_functions`, `htaccess`, `nginx`, `caddy`, `iis`, and `cloudflare`.
- The build and verification code only normalized a subset of those names.
- Result: the client build can derive an empty or wrong `active_enforcer`, which breaks verification and UI routing.

2. The client verifier was inferring too much
- The generated UI was selecting probe types from labels and partial hints.
- The master flow already has a concrete enforcement contract in the saved feature metadata.
- Result: the client build can end up checking for the wrong proof signal even when the feature is correctly implemented.

3. Packaged feature snapshots were not always hydrated with code
- The build snapshot could carry `code_ref` entries without the resolved `code` payload from the pattern library.
- Result: the generated client UI could show an empty technical snippet preview even when the master had the full enforcement code.

4. REST enumeration probes were being reused too broadly
- `Username Enumeration via wp-login.php` was still picking up a REST users endpoint probe in the client build.
- Result: the client would fail a valid login-enumeration implementation because it was testing the wrong attack surface.

5. Some SSoT entries are not aligned to the runtime contract
- `RISK-007` is catalogued as `fail2ban` in the JSON sources.
- The broader runtime stack historically falls back through Apache, PHP, and config-based enforcement.
- Result: a feature can be described one way in the catalog and validated another way in the client if the canonical enforcer is not normalized consistently.

6. Master metadata must remain the reference truth
- The user confirmed the master implementation flow is correct and should not be redesigned.
- The client build must adopt the same method instead of creating a parallel validation model.

### Likely Fix Direction

- Expand canonical enforcer normalization so every released platform family is represented.
- Hydrate packaged feature metadata with resolved code snippets before the client consumes it.
- Only generate REST user-enumeration probes when the feature actually declares a REST surface.
- Make the client build trust the master-derived feature metadata instead of re-infering the enforcement path from labels.
- If any SSoT entry is found to be inconsistent with the working master contract, correct the SSoT in the same pass.

### Immediate Risks

- `active_enforcer` can be empty or wrong for some released features.
- Client verification can still drift if it relies on partial alias tables.
- Risk-specific exceptions are not a safe long-term fix unless they are derived from the same canonical metadata contract.

### Review Note

This report does not assume the SSoT is correct in all cases. It treats the master runtime as the working reference and identifies where the SSoT needs to be brought back into alignment.
