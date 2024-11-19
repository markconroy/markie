# AI

AI (artificial intelligence) integration for Drupal - a unified framework for integrating various AI models and services into your Drupal site.

## Dependencies

- Drupal ^10.3 || ^11
- [Key module](https://www.drupal.org/project/key)

## Getting Started

1. Enable the AI module.
2. Choose and enable an AI provider submodule (e.g., OpenAI, Huggingface, Mistral).
3. Configure your API key in the Key module.
4. Configure the AI module at `/admin/config/ai/settings`.
5. Enable desired submodules for specific functionality.

## Develop for it

Check the [developers guide](developers/developer_information.md) for information on how to develop using the AI module.

## Documentation

This project uses MkDocs for documentation. The documentation source files are located in the `docs/` directory. To build the docs locally:

1. Install MkDocs: `pip install mkdocs mkdocs-material`
2. Run `mkdocs serve` in the project root
3. Open `http://localhost:8000` in your browser
