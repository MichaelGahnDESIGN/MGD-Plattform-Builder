# Deployment

## Separate coding from production deployment

A repository commit does not prove production is updated.

## Recommended flow

1. change
2. tests
3. review
4. backup/rollback check
5. staging
6. approval
7. production deploy
8. smoke tests
9. monitoring
10. documentation

## High-risk deploys

Require stronger gates for:

- database migrations
- auth/permission changes
- payment changes
- privacy/export changes
- large data transformations

## Rollback

Know what rollback means before deployment.

Database changes may require forward-fix rather than simple code rollback.

## Automation

CI/CD is welcome, but production should not be accidentally deployed by every commit unless that risk is intentionally accepted and well controlled.
