# Release Checklist (GitHub Public + WHMCS Marketplace)

## A. Code and Security

- [ ] `composer run check` passes
- [ ] No hardcoded secrets in repository
- [ ] CSRF token validation for admin write actions
- [ ] Write access restriction configured if needed (`restrict_write_admins`)

## B. Versioning and Migration

- [ ] Module version bumped in addon config
- [ ] Schema version updated (`schema_version` setting)
- [ ] Upgrade path tested (activate -> upgrade)

## C. Documentation

- [ ] README updated with features and install instructions
- [ ] CHANGELOG updated
- [ ] Privacy policy published
- [ ] DPA template reviewed by legal

## D. Packaging

- [ ] Run: `powershell -ExecutionPolicy Bypass -File .\scripts\package.ps1 -Version X.Y.Z`
- [ ] Verify archive contains `modules/addons/crmconnector/*`
- [ ] Upload package to GitHub Release artifacts

## E. Marketplace Readiness

- [ ] Product description and screenshots finalized
- [ ] Support/SLA and contact channels defined
- [ ] Data-processing disclosure aligned with actual behavior
- [ ] Compatibility matrix (WHMCS/PHP) confirmed

## F. Public Repo Hygiene

- [ ] Default branch is `main`
- [ ] CI workflows are green
- [ ] License chosen and added
- [ ] Tags/release notes created
