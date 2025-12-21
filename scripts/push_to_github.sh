#!/usr/bin/env bash
set -euo pipefail

REMOTE_URL=""
BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo main)
MSG="Update project files"
FORCE=${FORCE:-false}
USE_USER_REPO=false

# Simple arg parsing
while (("$#")); do
  case "$1" in
    -r|--remote-url)
      REMOTE_URL=$2; shift 2; ;;
    -b|--branch)
      BRANCH=$2; shift 2; ;;
    -m|--message)
      MSG=$2; shift 2; ;;
    --use-user-repo)
      USE_USER_REPO=true; shift 1; ;;
    -f|--force)
      FORCE=true; shift 1; ;;
    --)
      shift; break; ;;
    -*|--*=) echo "Unsupported flag $1"; exit 1; ;;
    *)
      # allow positional: remote, branch, message
      if [ -z "$REMOTE_URL" ]; then REMOTE_URL=$1; else if [ -z "$BRANCH" ]; then BRANCH=$1; else MSG=$1; fi; shift 1; ;;
  esac
done

if [ "$USE_USER_REPO" = true ]; then
  REMOTE_URL="https://github.com/mert6148/User-login.git"
fi

if ! command -v git >/dev/null 2>&1; then
  echo "Git is not installed. Aborting." >&2
  exit 1
fi

if [ -z "$REMOTE_URL" ]; then
  if git remote get-url origin >/dev/null 2>&1; then
    REMOTE_URL=$(git remote get-url origin)
  else
    echo "No remote specified and origin does not exist. Pass a remote url as first arg." >&2
    exit 1
  fi
fi

git add -A
if git diff --staged --quiet; then
  echo "No changes to commit."
  [ "$FORCE" = true ] || exit 0
fi

if ! git rev-parse --verify HEAD >/dev/null 2>&1; then
  # initial commit
  git commit --allow-empty -m "$MSG"
else
  git commit -m "$MSG" || true
fi

# Determine whether to push to origin or to a temporary remote
existing_origin=""
if git remote get-url origin >/dev/null 2>&1; then
  existing_origin=$(git remote get-url origin)
fi
# If REMOTE_URL is different than existing_origin, create a temp remote and push to it
remote_name=origin
temp_added=false
if [ -n "$REMOTE_URL" ] && [ "$REMOTE_URL" != "$existing_origin" ]; then
  # create a temporary remote name
  rnd=$(date +%s)-$RANDOM
  remote_name="temp-$rnd"
  git remote add "$remote_name" "$REMOTE_URL"
  temp_added=true
fi

# Ensure local branch exists
if ! git rev-parse --verify "$BRANCH" >/dev/null 2>&1; then
  echo "Local branch $BRANCH not found, creating from current HEAD..."
  git branch "$BRANCH"
fi

echo "Pushing to $remote_name/$BRANCH..."
git push -u "$remote_name" "$BRANCH"
echo "Pushed to $remote_name/$BRANCH"

if [ "$temp_added" = true ]; then
  git remote remove "$remote_name"
fi
