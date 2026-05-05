## Build Generator Review

The build generator is security-aware, but it is not secure in every aspect yet. It already enforces some important runtime boundaries, but there are still bypass and integrity gaps that prevent it from being treated as fully hardened.

### Findings

1. **High: wildcard domain matching is too permissive**
   - The generated build uses substring-style wildcard checks for domain acceptance.
   - That means a host can potentially satisfy a lock on a shorter domain name without being the intended domain.
   - This is not strong enough for license enforcement or client build isolation.

2. **High: build integrity is not tamper-evident**
   - The generator embeds build data in `VAPTSECURE_CONFIG_B64`.
   - That payload is only base64-encoded, not signed or verified.
   - The package does not appear to include a signature, checksum validation, or other integrity guarantee, so a modified artifact would not be detected reliably.

3. **Medium: public test endpoints ship in the runtime**
   - The REST layer exposes endpoints such as `ping` and `reset-limit` with public access.
   - Those routes are useful for diagnostics, but they weaken the security story if they remain present in a generated client build.
   - A secure client build should strip or hard-disable endpoints that are not required for the released runtime.

4. **Medium: generated build security depends on rewrite success**
   - The generator rewrites the main plugin file to remove builder behavior and add fail-closed guards.
   - That is a valid enforcement strategy, but it means the final security posture depends on the rewrite step succeeding exactly as intended.
   - If the rewrite drifts or is partially applied, the generated artifact can retain builder-mode behavior.

5. **Low but real: sensitive metadata is embedded into shipped config**
   - The config payload can include feature metadata, implementation data, and alert email details.
   - Even if that data is not directly executable, it increases the amount of sensitive state present inside the distributed artifact.
   - Minimizing the shipped payload would reduce exposure.

### What Is Already Good

- The generated build fails closed when the expected config file is missing.
- Superadmin and workbench renderers are stripped from generated client builds.
- Feature access is restricted through build-time constants and domain-lock checks.
- The runtime enforcer still applies permission checks around privileged REST operations.

### Conclusion

The current generator is enforcing, but it is not yet defensibly hardened in every aspect. The main gaps are:

- wildcard/domain validation,
- package integrity and tamper evidence,
- removal of public test surfaces from client builds,
- and reducing dependence on a fragile rewrite step.

### Recommended Next Hardening Pass

1. Tighten domain matching so wildcard approval is exact and fail-closed.
2. Add a signed or checksum-based integrity marker to the generated package.
3. Strip or disable public test endpoints from client builds.
4. Reduce the amount of sensitive metadata embedded in `VAPTSECURE_CONFIG_B64`.
