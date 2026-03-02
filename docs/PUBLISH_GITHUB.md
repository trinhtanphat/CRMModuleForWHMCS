# Publish to GitHub (Public)

## 1. Initialize and commit

```powershell
git init
git add .
git commit -m "feat: initial WHMCS CRM connector module"
```

## 2. Create remote repository

Create a new public repository in your GitHub account (for example: `trinhtanphat/CRMModuleForWHMCS`).

## 3. Push code

```powershell
git branch -M main
git remote add origin https://github.com/trinhtanphat/CRMModuleForWHMCS.git
git push -u origin main
```

## 4. Optional: create tag + release

```powershell
git tag v1.0.0
git push origin v1.0.0
```

Use GitHub Releases and attach zip artifact from workflow or local `dist` package.
