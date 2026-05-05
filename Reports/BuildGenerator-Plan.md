## Build Generator Hardening Plan

This plan is based on the current review of the build generator and is meant for review before implementation.

### Goal

Make generated client builds enforcing by default and materially stronger in security posture by reducing bypass paths, adding package integrity, and removing unnecessary runtime exposure.

The configuration model should be split into:

- `Default`, which is generated at build time and treated as protected, immutable baseline data
- `Extended`, which is optional client-side overlay data for additive or local customizations

Security-critical values must come from `Default` and must not be silently replaced by `Extended`.

### Scope

In scope:

- `includes/class-vaptsecure-build.php`
- `includes/class-vaptsecure-rest.php`
- `vaptsecure.php`
- any generated build metadata or runtime guard behavior that directly affects client build security

Out of scope for this slice:

- redesigning the full plugin architecture
- changing unrelated admin UI flows
- broad feature catalog edits

### Current Problems To Fix

1. Wildcard domain checks need strict apex-plus-subdomain handling.
2. Build artifacts have no tamper-evident integrity layer.
3. Public test/diagnostic endpoints are still present in runtime code.
4. The generated build depends on a rewrite pass that can drift or fail partially.
5. Shipped config payloads carry more sensitive state than necessary.
6. The current config model does not separate protected baseline data from client-extensible overlay data.

### Proposed Implementation Phases

#### Phase 1: Tighten runtime enforcement boundaries

- Replace substring-style wildcard acceptance with strict, fail-closed matching.
- Allow the apex domain plus valid subdomains of the same registered base domain.
- Make domain validation explicitly normalize hostnames before comparison.
- Ensure mismatched domains always disable the build path rather than degrading silently.

Deliverable:

- deterministic domain enforcement for exact targets and apex-plus-subdomain wildcard targets
- no accidental host matches from partial substrings or lookalike domains

#### Phase 2: Add build integrity protection

- Add a build manifest field that can be verified after generation.
- Introduce a checksum or signature-style integrity marker for the packaged artifact.
- Make the runtime reject or warn on tampered config payloads when the integrity check fails.
- Split the configuration into `Default` and `Extended` sections.
- Treat `Default` as the signed or checksum-protected baseline.
- Allow `Extended` only for additive, policy-limited client customization.

Deliverable:

- package-level integrity signal
- detectable modification of the shipped build
- protected baseline config with separate extension overlay

#### Phase 3: Strip or disable unsafe runtime surfaces

- Remove public test endpoints from generated client builds.
- Keep diagnostic endpoints only when they are explicitly required and protected.
- Ensure client builds cannot expose builder-only or testing-only routes.

Deliverable:

- smaller and safer client runtime surface
- reduced attack exposure in shipped plugins

#### Phase 4: Reduce sensitive payload exposure

- Minimize what is embedded into `VAPTSECURE_CONFIG_B64`.
- Keep only the fields needed for runtime enforcement.
- Move non-essential metadata out of the shipped payload when possible.
- Keep `Default` lean and security-critical only.
- Keep `Extended` separate so client additions do not expand the protected baseline.

Deliverable:

- leaner config payload
- less sensitive data present in the artifact

#### Phase 5: Verify rewrite safety and fail-closed behavior

- Confirm the main-file rewrite still strips builder-mode behavior after the changes.
- Verify missing config continues to disable the build.
- Verify build generation fails clearly if a security-critical rewrite step cannot complete.
- Verify `Extended` cannot override protected `Default` values.
- Verify removal or corruption of `Default` fails closed.

Deliverable:

- hardened generation path
- explicit failure behavior for incomplete build assembly
- clear trust boundary between baseline and overlay config

### Verification Plan

Before any implementation is considered complete:

1. Confirm exact-domain builds still work for the intended host.
2. Confirm wildcard builds allow the apex domain and all valid subdomains of the intended domain.
3. Confirm wildcard builds reject unintended substring matches and lookalike domains.
4. Confirm public endpoints are absent or blocked in generated client builds.
5. Confirm tampered build metadata is detected.
6. Confirm missing config still fails closed.
7. Confirm the generated build remains usable on the intended domain after hardening.
8. Confirm `Extended` data can add approved behavior without replacing protected `Default` settings.
9. Confirm tampering with `Default` disables the build or causes a clear integrity failure.

### Risks

- Over-tightening domain matching could break legitimate wildcard installs.
- Integrity enforcement could require changes to both generator and runtime code.
- Removing public endpoints may affect existing diagnostics workflows.
- Payload minimization could require a follow-up pass on any code that expects the removed fields.
- A poorly designed `Default` / `Extended` merge order could let overlay data weaken baseline protections.

### Review Gate

Do not implement until this plan is reviewed and approved. If you want changes to the order, scope, or depth of hardening, revise this document first.
