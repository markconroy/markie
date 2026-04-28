# Organizing AI Modules & Features

The Drupal AI ecosystem uses a three-tier hierarchy to organize modules, submodules, and features. This structure helps users understand what each component does and quickly locate what they need.

## Top-Level Three-Tier Hierarchy

**Infrastructure** → **Tools & Automation** → **Features** 

Each tier builds on the previous: Infrastructure enables Tools to execute, and Tools power the Features users interact with.

### Subcategories 

Infrastructure and Features use subcategories to group related components. Tools & Automation is currently a single list but may add subcategories as the ecosystem grows.

- **Infrastructure**
    - AI Infrastructure
    - Vector Search Infrastructure
- **Tools & Automation**
- **Features**
    - Content Creation & Support
    - People, Accounts & Users
    - Safety & Compliance
    - Search & Discovery
    - Site Building & Design


## Infrastructure

Backend configuration and foundational services that other AI components depend on.

Components at this level must be configured before AI features can function. Section names should include "Infrastructure" for clarity.

**Examples:**

- **AI Infrastructure**
    - AI Provider Connections (API keys, authentication)  
    - Default Model Configuration  
    - Prompt Template Library  
- **Vector Search Infrastructure** 
    - Embedding Services
    - Vector Database Connections  

## Tools & Automation

Operational tools that execute tasks and automate workflows in the background.

These are the "doers" - not pure configuration, not end-user interfaces, but the working layer in between.

**Examples:**

- **Tools & Automation**
    - AI Agents (execute Drupal operations)  
    - AI Assistants (conversational logic and configuration)  
    - AI Automation Workflows (chained operations)  
    - AI Moderation Guardrails (automatic safety filtering)

**Key distinction:** Configuration for these tools lives here; the user interfaces appear in Features.

## Features

End-user facing capabilities and site enhancements. If users directly interact with it, it belongs here.

Feature names describe their function - avoid "Infrastructure" or "Tools" in these labels.

**Feature categories:**

- **Content Creation & Support** - Generation, review, SEO tools, translation, personalization  
- **People, Accounts & Users** - Chat widgets, user profiling, account assistance  
- **Safety & Compliance** - Review dashboards, observability, spam detection, privacy controls  
- **Search & Discovery** - Search interfaces, recommendations, filters  
- **Site Building & Design** - Page builders, theme assistants, layout tools

## Ordering Rules

1. **Top-level sections:** Infrastructure → Tools & Automation → Features  
2. **Within sections:** Within each tier and subcategory, items are listed alphabetically

## Quick Decision Framework

When deciding where a component belongs:

| Question | Answer | Place it in... |
| :---- | :---- | :---- |
| Is it necessary to set up before AI can work? | Yes | Infrastructure |
| Does it operate automatically in the background? | Yes | Tools & Automation |
| Do users interact with it directly? | Yes | Features |

## When It's Not Clear

Some components span multiple tiers. When this happens, apply the **80/20 rule**: categorize based on where the majority of usage occurs.

**Example:** The Context Control Center has an interface where users upload context documents, but 80% of its activity happens in the background. Agents automatically attach context information during agent operations. The occasional configuration updates don't make it a Feature, so we categorize it in Tools & Automation.

**When in doubt:** Ask "Where does most of the work happen?" and place the component there.