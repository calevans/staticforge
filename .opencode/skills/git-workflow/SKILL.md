---
name: git-workflow
description: Git branching strategy, commit message standards, and workflow for this project. Use when making commits or managing branches.
---

# Git Workflow

## When to use this
Use this skill when you need to:
- Create a new branch for a feature, bugfix, or hotfix.
- Write a commit message that complies with project standards.
- Merge code or understand the branching strategy.

## Branch Strategy

### Main Branches
- **main** - Production-ready code only
- **develop** - Integration branch for features

### Supporting Branches
- **feature/** - New features (e.g., `feature/watchlist-alerts`)
- **fix/** - Bug fixes (e.g., `fix/api-rate-limit`)
- **hotfix/** - Urgent production fixes (e.g., `hotfix/security-patch`)
- **refactor/** - Code refactoring (e.g., `refactor/database-layer`)

## Workflow

### Starting New Work
```bash
# Update develop
git checkout develop
git pull origin develop

# Create feature branch
git checkout -b feature/stock-search

# Work on feature...
```

### Making Commits
Follow conventional commit format:
```
type(scope): subject

body (optional)

footer (optional)
```

#### Types
- **feat**: New feature
- **fix**: Bug fix
- **refactor**: Code refactoring
- **perf**: Performance improvement
- **test**: Adding or updating tests
- **docs**: Documentation changes
- **style**: Code formatting (no logic change)
- **chore**: Maintenance tasks
- **security**: Security fixes

#### Scopes
Common scopes for this project:
- **api**: API endpoints
- **frontend**: UI/JavaScript
- **database**: Schema or queries
- **auth**: Authentication/authorization
- **analysis**: Stock analysis features
- **portfolio**: Portfolio management
- **watchlist**: Watchlist features

#### Examples
```bash
# Good commits
git commit -m "feat(api): add stock search endpoint"
git commit -m "fix(database): correct OHLCV constraint check"
git commit -m "refactor(analysis): extract RSI calculation to helper"
git commit -m "perf(database): add index on stock_prices(symbol, date)"

# With body
git commit -m "feat(watchlist): add email alerts

- Implement background job for price monitoring
- Send email when price crosses threshold
- Add user preferences for alert frequency"

# Security fix
git commit -m "security(api): sanitize SQL inputs

Fixes potential SQL injection in stock search.
Closes #123"
```

### Committing Changes
```bash
# Stage changes
git add path/to/file

# Stage all changes (use carefully)
git add .

# Commit with message
git commit -m "feat(portfolio): add gain/loss calculation"

# Amend last commit (before pushing)
git commit --amend
```

### Pushing Changes
```bash
# Push feature branch
git push origin feature/stock-search

# Force push (only if you're sure)
git push --force-with-lease origin feature/stock-search
```

### Merging Features

#### Option 1: Merge to Develop
```bash
# Update your branch with latest develop
git checkout feature/stock-search
git merge develop

# Or rebase (cleaner history)
git rebase develop

# Merge into develop
git checkout develop
git merge --no-ff feature/stock-search
git push origin develop
```

#### Option 2: Pull Request
1. Push branch to origin
2. Create PR on GitHub/GitLab
3. Request code review
4. Address feedback
5. Squash and merge

### Releasing to Production
```bash
# From develop, create release
git checkout develop
git pull origin develop

# Merge to main
git checkout main
git merge --no-ff develop

# Tag release
git tag -a v1.2.0 -m "Release version 1.2.0"
git push origin main --tags
```

### Hotfix Workflow
```bash
# Create hotfix from main
git checkout main
git checkout -b hotfix/critical-security-fix

# Make fix and commit
git commit -m "security(api): fix SQL injection vulnerability"

# Merge to main
git checkout main
git merge --no-ff hotfix/critical-security-fix
git tag -a v1.2.1 -m "Hotfix v1.2.1"

# Also merge to develop
git checkout develop
git merge --no-ff hotfix/critical-security-fix

# Push everything
git push origin main develop --tags

# Delete hotfix branch
git branch -d hotfix/critical-security-fix
```

## Commit Message Guidelines

### Subject Line
- Use imperative mood ("add feature" not "added feature")
- No period at the end
- 50 characters or less
- Capitalize first letter

### Body
- Separate from subject with blank line
- Wrap at 72 characters
- Explain **what** and **why**, not **how**
- Use bullet points with `-` or `*`

### Footer
- Reference issues: `Closes #123`, `Fixes #456`
- Breaking changes: `BREAKING CHANGE: ...`
- Co-authors: `Co-authored-by: Name <email>`

## What to Commit

### DO Commit
- Source code
- Configuration templates (`.env.example`)
- Database migrations
- Documentation
- Tests
- Build scripts

### DON'T Commit
- `.env` files with secrets
- Database files (`*.db`)
- Cache files
- Log files
- IDE files (`.idea/`, `.vscode/`)
- Temporary files
- Dependencies (`vendor/`, `node_modules/`)
- Large binary files

## .gitignore Template
```gitignore
# Environment
.env
.env.local

# Database
*.db
*.sqlite
data/

# Cache
cache/
*.cache

# Logs
logs/
*.log

# Dependencies
vendor/
node_modules/

# IDE
.vscode/
.idea/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db

# Temporary
tmp/
temp/
*.tmp
```

## Useful Git Commands

### Viewing History
```bash
# Pretty log
git log --oneline --graph --decorate --all

# Show changes in commit
git show <commit-hash>

# File history
git log -p path/to/file
```

### Undoing Changes
```bash
# Discard uncommitted changes
git restore path/to/file

# Unstage file
git restore --staged path/to/file

# Undo last commit (keep changes)
git reset --soft HEAD~1

# Undo last commit (discard changes)
git reset --hard HEAD~1

# Revert a commit (safe for shared history)
git revert <commit-hash>
```

### Stashing
```bash
# Stash changes
git stash

# Stash with message
git stash save "WIP: working on feature"

# List stashes
git stash list

# Apply stash
git stash apply

# Apply and drop
git stash pop

# Drop stash
git stash drop stash@{0}
```

### Branching
```bash
# List branches
git branch -a

# Delete local branch
git branch -d feature/old-feature

# Delete remote branch
git push origin --delete feature/old-feature

# Rename branch
git branch -m old-name new-name
```

### Cleaning
```bash
# Remove untracked files (dry run)
git clean -n

# Remove untracked files
git clean -f

# Remove untracked files and directories
git clean -fd
```

## Code Review Checklist

Before requesting review:
- [ ] Code follows project conventions
- [ ] All tests pass
- [ ] No console.log or debug code
- [ ] Documentation updated
- [ ] Commit messages follow format
- [ ] No merge conflicts
- [ ] No sensitive data committed

## Emergency Recovery

### Lost Commits
```bash
# Find lost commits
git reflog

# Restore commit
git checkout <commit-hash>
git branch recovered-work
```

### Corrupted Repository
```bash
# Verify repository
git fsck

# If corrupted, re-clone
cd ..
git clone <repository-url> stock-picker-new
cd stock-picker-new
```

## Best Practices

1. **Commit often** - Small, focused commits are better
2. **Write clear messages** - Your future self will thank you
3. **Review before committing** - Use `git diff` to check changes
4. **Pull before push** - Avoid merge conflicts
5. **Test before committing** - Don't break the build
6. **Use branches** - Keep main stable
7. **Don't commit secrets** - Use .env files
8. **Keep history clean** - Squash/rebase when appropriate

