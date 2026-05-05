## Build Generator Issues and Intended Fixes

This document explains the main security and enforcement gaps in the current build generator and describes, where possible, how they should be fixed.

### 1. Wildcard domain checks need strict apex-plus-subdomain handling

#### What this means

The build should allow:

- the apex domain, for example `hermasnet.com`
- valid subdomains of that apex, for example `www.hermasnet.com` or `app.hermasnet.com`

It should not allow:

- lookalike domains, for example `hermasnet.com.evil.com`
- substring-based matches, for example a host that only contains `hermasnet` somewhere in the name

#### Why it matters

Wildcard enforcement is supposed to expand the allowed domain set safely. If the rule is too loose, the build can be used on a host that only looks related to the intended domain.

#### Intended fix

- Normalize the host before comparison.
- Compare against the apex domain explicitly.
- For wildcard builds, allow only hosts that are exact matches or proper subdomains of the same registered base domain.
- Use suffix-style matching with a dot boundary, not substring matching.

### 2. Build artifacts have no tamper-evident integrity layer

#### What this means

The generated build currently carries encoded runtime data, but it does not clearly prove that the artifact has not been changed after generation.

Base64 encoding is not integrity protection. It only encodes data. It does not detect modification.

#### Why it matters

If someone edits the generated plugin after it is built, the runtime may still trust it. That weakens the security and licensing model because the package can be altered without detection.

#### Intended fix

- Add a build-time integrity marker, such as a checksum or signed manifest.
- Verify that marker at runtime or at load time.
- Fail closed if the artifact does not match what was produced by the generator.
- Keep the integrity check tied to the exact payload and version being shipped.

### 3. Public test/diagnostic endpoints are still present in runtime code

#### What this means

Some runtime routes are exposed for testing or diagnostics, such as public ping-style endpoints or reset helpers.

#### Why it matters

Even if these endpoints are convenient during development, they create unnecessary surface area in a client build. Any route that is not required for the released runtime should not remain publicly reachable.

#### Intended fix

- Remove public test endpoints from generated client builds.
- Keep diagnostic endpoints only when they are explicitly needed and protected.
- Make sure client builds expose only the minimum runtime routes required for operation.

### 4. The generated build depends on a rewrite pass that can drift or fail partially

#### What this means

The generator does not simply package files. It also rewrites the main plugin file to strip builder behavior and inject fail-closed guards.

That is useful, but it means the final security result depends on the rewrite step being correct every time.

#### Why it matters

If the rewrite misses a block, applies in the wrong place, or fails partially, the generated artifact can retain builder-mode behavior or other unsafe runtime paths.

#### Intended fix

- Keep the rewrite step, but make it stricter and easier to verify.
- Add explicit post-rewrite validation.
- Fail the build if a security-critical rewrite step does not complete successfully.
- Confirm the generated client build does not retain builder/admin behavior.

### 5. Shipped config payloads carry more sensitive state than necessary

#### What this means

The build payload can include items such as:

- feature metadata
- implementation data
- alert email details
- license and domain settings

Not all of that is required just to enforce the build at runtime.

#### Why it matters

The more sensitive data is embedded into the shipped artifact, the more exposure exists if the package is copied, inspected, or modified.

#### Intended fix

- Reduce the payload to only what the runtime actually needs.
- Move non-essential metadata out of the shipped config when possible.
- Keep sensitive references out of the package unless they are required for enforcement.

### Summary

The generator already has some fail-closed behavior, but the next hardening pass should focus on:

- strict domain matching,
- build integrity,
- removing public runtime surfaces,
- making rewrite failure visible,
- and minimizing the amount of sensitive data shipped in the build.

### Practical Next Step

The safest implementation order is:

1. Fix the domain matching rule.
2. Add build integrity validation.
3. Remove or gate public endpoints.
4. Add rewrite validation and fail-closed behavior.
5. Trim the shipped config payload.
