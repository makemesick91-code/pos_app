# 90 — Release Evidence & GO Tag

The GO/NO-GO discipline for cutting a release tag on Aish POS.

## Preconditions for GO (all required)
1. Authoritative `pull_request` CI green (rule 70) and merged to `main`.
2. Successful deploy to the VPS Aish stack (php8.5 pool `aish-pos`, port 8080).
3. Runtime verification passed on the deployed build (health/live, health/ready, smoke,
   relevant go-no-go command).
4. DaengtisiaMS non-regression confirmed — DMS unaffected (rule 80).
5. Real, non-placeholder evidence captured (logs/output/screens of the above).

If any precondition is unmet — including public plaintext admin exposure — the release is
NO-GO. Do not tag.

## Commit equality
- The final release commit must be identical across local, origin, and the VPS checkout.
  Verify the three match before tagging; a drift is NO-GO.

## Tag format & immutability
- GO tags are annotated tags on the verified release commit.
- Existing GO tags are immutable: never move, delete, re-point, or overwrite a published
  GO tag. A new release gets a new tag.

## Evidence record
- Record the evidence and the GO decision (who, when, what was verified) alongside the
  release, consistent with prior sprints' evidence-closure practice.
- No GO tag may be pushed on placeholder or assumed evidence. Absence of proof = NO-GO.
