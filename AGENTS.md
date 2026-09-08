# Project collaboration instructions

## GitHub handoff

- Whenever a task changes code, configuration, tests, documentation, or project assets, end the final response with copy-paste-ready commands for Windows Command Prompt (`cmd.exe`) that let the user commit and push those exact changes to GitHub.
- Tailor the `git add` paths and commit message to the completed task. Do not use a generic placeholder commit message.
- Include the push command for the current branch and configured `origin` remote. Verify both when needed instead of assuming them.
- Format the commands in a fenced `bat` code block. Use CMD syntax, not PowerShell syntax.
- Do not automatically commit or push unless the user explicitly asks the agent to do so.
- If the task made no file changes, explicitly say that no Git commands are needed.
