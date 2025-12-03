# GitHub Copilot Instructions

## Project Context

**CRITICAL**: Always read and follow `/AGENTS.md` for all project-specific conventions, structure, and workflows.

## Key Requirements

- This is a Drupal 11 project (markconroy/markie)
- Custom module prefix: `markie_`
- Custom theme: `markconroy` (in `/web/themes/custom/markconroy/`)
- Web root: `web/`
- Use DDEV for all commands: `ddev drush`, `ddev composer`, etc.
- Check AGENTS.md for:
  - Code standards and patterns
  - Entity structure (Article, Page, Speaking content types)
  - Custom modules and their purposes
  - Theme structure and libraries
  - Common tasks and troubleshooting

## Response Style

- Code over explanations
- Assume Drupal expertise
- Use Drupal APIs, not generic PHP
- Follow conventions in AGENTS.md
- Reference AGENTS.md sections when relevant

## Before Making Changes

- Check AGENTS.md for existing patterns
- Use correct module/theme naming conventions
- Follow the Git Workflow section
- Log significant changes in AGENTS.md "Tasks and Problems Log"
