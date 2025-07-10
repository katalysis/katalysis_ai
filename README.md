# Katalysis AI Package for Concrete CMS

A test AI integration package for Concrete CMS that provides both basic AI chat functionality and advanced Retrieval-Augmented Generation (RAG) capabilities with content indexing via a Concrete CMS Task.

## Features

### 🤖 Example AI Chat Interface
- **Dual Mode Support**: Toggle between basic AI mode and RAG (Retrieval-Augmented Generation) mode
- **Multiple AI Providers**: Support for OpenAI, Anthropic, and Ollama
- **Real-time Chat**: Interactive chat interface with message history
- **Context-Aware Responses**: AI responses tailored to your website content

### 🔍 RAG (Retrieval-Augmented Generation)
- **Content Indexing**: Automatically indexes your Concrete CMS pages for AI context
- **Smart Search**: Vector-based similarity search for relevant content retrieval
- **Context Integration**: AI responses are enhanced with your website's actual content
- **Metadata Support**: Stores page URLs and metadata for reference


## Installation

1. Install the `katalysis_neuron_ai` package first (foundation layer)
2. Install this `katalysis_ai` package
3. Configure your AI provider API keys in Dashboard > System > Basics > AI
4. Run the "Build RAG Index" task to index your content

## Configuration

### Required Settings
- **OpenAI API Key**: For AI responses and embeddings
- **OpenAI Model**: Default is `gpt-4o-mini`
- **Anthropic Key**: Optional alternative AI provider
- **Ollama URL**: Optional local AI provider

### Automated Tasks
- **Build RAG Index**: Automatically rebuilds the content index
- **Content Filtering**: Excludes system pages and short content
- **Vector Store Management**: Handles document embeddings and storage



## Dependencies

- Concrete CMS 9.3+
- `katalysis_neuron_ai` package (NeuronAI framework)
- PHP 8.1+

## Architecture

This package builds on the `katalysis_neuron_ai` foundation package, providing:
- User interface and chat functionality
- Content indexing and RAG implementation
- Dashboard integration and task management
- Concrete CMS-specific configurations and workflows
