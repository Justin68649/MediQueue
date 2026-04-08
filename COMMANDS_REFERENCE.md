# Quick Reference: GitHub & Vercel Commands

## Initial Setup (One-time)

```bash
# Initialize git (if not done)
git init

# Create .env from template
copy .env.example .env
# Edit .env with your credentials

# Add files to staging
git add .

# Create initial commit
git commit -m "Initial commit: MediQueue"

# Add GitHub remote
git remote add origin https://github.com/YOUR_USERNAME/mediqueue.git

# Push to GitHub
git push -u origin main
```

## Daily Development Workflow

### After making changes:

```bash
# Check status
git status

# Stage specific files
git add filename.php

# Or stage all changes
git add .

# Commit changes
git commit -m "Brief description of changes"

# Push to GitHub (automatically triggers Vercel deployment)
git push origin main
```

## Common Git Commands

```bash
# View commit history
git log --oneline -10

# View differences
git diff

# Revert last commit (before push)
git reset --soft HEAD~1

# Create new branch (for features)
git checkout -b feature/awesome-feature

# Switch back to main
git checkout main

# Delete local branch
git branch -d feature/awesome-feature

# Delete remote branch
git push origin --delete feature/awesome-feature
```

## Vercel Deployment

```bash
# Install Vercel CLI
npm install -g vercel

# Deploy manually (optional)
vercel

# View deployments
vercel ls

# Check logs
vercel logs <deployment-url>
```

## Environment Variables

### Local Development (.env)
```
DB_HOST=localhost
DB_NAME=mediqueue_db
DB_USER=root
DB_PASS=
APP_ENV=development
DEBUG=true
```

### Production (Vercel Dashboard)
```
DB_HOST=your_remote_db_host
DB_NAME=mediqueue_db
DB_USER=your_db_user
DB_PASS=your_secure_password
APP_URL=https://your-app.vercel.app
APP_ENV=production
DEBUG=false
```

## Database Backup

```bash
# Export current database
mysqldump -u root -p mediqueue_db > backup_$(date +%Y%m%d).sql

# Import to remote
mysql -h remote_host -u user -p mediqueue_db < backup.sql
```

## Emergency: Rollback Deployment

```bash
# View previous commits
git log --oneline

# Revert to specific commit
git revert <commit-hash>
git push origin main

# Or force push (use carefully!)
git reset --hard <commit-hash>
git push origin main --force
```

## Useful Links

- **GitHub**: https://github.com
- **Vercel Dashboard**: https://vercel.com/dashboard
- **Vercel Docs**: https://vercel.com/docs
- **Git Documentation**: https://git-scm.com/doc

---

**Remember:** Never commit `.env` file! It contains sensitive credentials.

