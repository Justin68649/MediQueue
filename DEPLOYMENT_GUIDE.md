# MediQueue Deployment Guide: GitHub & Vercel

## Overview

This guide walks you through deploying MediQueue to Vercel with GitHub integration. Vercel supports PHP applications and provides automatic deployments from GitHub.

## Prerequisites

- GitHub account ([github.com](https://github.com))
- Vercel account ([vercel.com](https://vercel.com))
- Git installed on your machine
- Your database hosted externally (not local/localhost)

## Part 1: Prepare Your Repository Locally

### Step 1: Initialize Git (if not already done)

```bash
cd c:\xampp\htdocs\MediQueue
git init
git add .
git commit -m "Initial commit: MediQueue application"
```

### Step 2: Create .env File

Copy `.env.example` to `.env` and update with your credentials:

```bash
cp .env.example .env
```

Edit `.env` with your database details:
```
DB_HOST=your_remote_db_host
DB_NAME=mediqueue_db
DB_USER=your_db_user
DB_PASS=your_db_password
APP_URL=https://your-vercel-domain.vercel.app
APP_ENV=production
```

**DO NOT commit `.env` to GitHub** - it's already in `.gitignore`

## Part 2: Set Up GitHub Repository

### Step 1: Create Repository on GitHub

1. Go to [github.com/new](https://github.com/new)
2. Repository name: `mediqueue` (or your preferred name)
3. Description: "Medical Queue Management System"
4. Choose **Public** or **Private** (Private is recommended for sensitive data)
5. Click **Create repository**

### Step 2: Add Remote and Push

```bash
git remote add origin https://github.com/YOUR_USERNAME/mediqueue.git
git branch -M main
git push -u origin main
```

Replace `YOUR_USERNAME` with your actual GitHub username.

### Step 3: (.gitignore is already configured)

Your `.gitignore` file is already set up to exclude:
- `.env` (sensitive data)
- `config/config.local.php`
- `vendor/` (dependencies)
- `cache/`
- IDE files

## Part 3: Deploy to Vercel

### Step 1: Connect GitHub to Vercel

1. Go to [vercel.com/dashboard](https://vercel.com/dashboard)
2. Click **Import Project** or **New Project**
3. Select **Import Git Repository**
4. Copy your GitHub repo URL: `https://github.com/YOUR_USERNAME/mediqueue.git`
5. Paste and click **Continue**

### Step 2: Configure Project

1. **Framework Preset**: Select **Other** (PHP)
2. **Root Directory**: Leave as `/` (default)
3. **Build Command**: Leave empty (PHP doesn't need building)

### Step 3: Add Environment Variables

Click **Add Environment Variables** and add all these:

```
DB_HOST        = your_remote_db_host
DB_NAME        = mediqueue_db  
DB_USER        = your_db_user
DB_PASS        = your_db_password
APP_URL        = https://your-project.vercel.app (Vercel will give you this)
APP_ENV        = production
TIMEZONE       = Africa/Nairobi
DEBUG          = false
EMAIL_ENABLED  = true
MAIL_HOST      = smtp.mailtrap.io (if using email)
MAIL_PORT      = 2525
MAIL_USER      = your_mail_user
MAIL_PASS      = your_mail_password
```

### Step 4: Deploy

Click **Deploy** and wait for the build to complete!

Your app will be available at: `https://your-project.vercel.app`

## Part 4: Database Preparation

### Important: Move to Remote Database

Since Vercel instances are ephemeral, your database MUST be on a remote server:

#### Option A: AWS RDS
```sql
-- Create RDS instance
-- Use the endpoint as DB_HOST in .env
```

#### Option B: DigitalOcean Managed Database
1. Go to DigitalOcean dashboard
2. Create new MySQL database
3. Get connection string from "Connection Details"
4. Use credentials in `.env`

#### Option C: Heroku PostgreSQL (if migrating)
- Would require code changes from MySQL to PostgreSQL

### Migrate Your Database

1. Export current database:
```bash
mysqldump -u root -p mediqueue_db > backup.sql
```

2. Import to remote server:
```bash
mysql -h your_rds_endpoint -u dbuser -p mediqueue_db < backup.sql
```

## Part 5: Automatic Deployments

After the initial setup:

1. **Every push to `main` branch** triggers automatic deployment
2. **Check deployment status** at [vercel.com/dashboard](https://vercel.com/dashboard)
3. Each deployment gets a unique URL for preview

### To deploy updates:

```bash
git add .
git commit -m "Update: description of changes"
git push origin main
```

Vercel will automatically deploy!

## Part 6: Custom Domain (Optional)

1. In Vercel dashboard, go to **Settings** → **Domains**
2. Add your custom domain (e.g., `mediqueue.com`)
3. Update DNS records with instructions Vercel provides
4. Update `APP_URL` in environment variables

## Troubleshooting

### Build Fails

- Check PHP version compatibility (Vercel uses PHP 8.2)
- Review build logs in Vercel dashboard
- Ensure all files are committed to GitHub

### Database Connection Error

- Verify remote database is accessible
- Check `.env` credentials in Vercel settings
- Ensure database server allows connections from Vercel IP (usually 0.0.0.0/0)

### CORS Issues

Your `config.php` already handles CORS, but update `APP_URL` in both:
- `.env` file
- Vercel environment variables

### Session Issues

- Vercel instances are stateless; sessions may not persist across deploys
- Consider implementing database-session storage instead of file-based sessions

## Monitoring

Set up alerts in Vercel dashboard:
- Failed deployments
- Critical errors

Set up monitoring for your database:
- Connection logs
- Performance metrics

## Summary

✅ **Created files:**
- `.gitignore` - Excludes sensitive files
- `.env.example` - Template for environment variables
- `vercel.json` - Vercel deployment config
- Updated `config/constants.php` - Uses environment variables

✅ **Next steps:**
1. Create GitHub repository
2. Push code to GitHub
3. Connect to Vercel
4. Set environment variables
5. Deploy!

## Additional Resources

- [Vercel PHP Documentation](https://vercel.com/docs/functions/serverless-functions/runtimes/php)
- [GitHub Desktop](https://desktop.github.com/) - Easy GUI for Git
- [Environment Variables Best Practices](https://vercel.com/docs/concepts/projects/environment-variables)

