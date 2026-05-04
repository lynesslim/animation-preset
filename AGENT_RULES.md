# Agent Rules

## Committing to Git

Every time the user asks you to commit and push changes to the repository, you **MUST** do the following:

1. Stop and ask the user if they would like to publish this commit as a new version release.
2. If the user says **YES**:
   - Increment the `Version:` number in `supercraft-animations.php`.
   - Then proceed with the commit and push.
3. If the user says **NO**:
   - Proceed with the commit and push without modifying the version number.

**Do not** automatically push to git without asking this question first!
