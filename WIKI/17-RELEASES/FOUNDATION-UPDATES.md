# Foundation Updates

Adopting projects should record the foundation version they use.

## Before update

1. read CHANGELOG
2. compare schema changes
3. run `/platform update` if available
4. produce a migration plan
5. update project profile only after review

## Compatibility

Changes may be:

- documentation-only
- additive
- recommended migration
- breaking schema/convention change

## No blind synchronization

The foundation should never overwrite project-specific decisions automatically.

Updates should propose changes and preserve local architecture unless the project explicitly adopts the new recommendation.
