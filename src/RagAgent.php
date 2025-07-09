<?php

namespace KatalysisAi;

use Concrete\Core\Support\Facade\Config;
use NeuronAI\SystemPrompt;
use NeuronAI\Chat\History\FileChatHistory;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\Embeddings\OpenAIEmbeddingsProvider;
use NeuronAI\RAG\RAG;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;
use NeuronAI\RAG\DataLoader\StringDataLoader;
use NeuronAI\RAG\VectorStore\FileVectorStore;



class RagAgent extends RAG
{
    
    protected function provider(): AIProviderInterface
    {
        // return an AI provider (Anthropic, OpenAI, Ollama, Gemini, etc.)
        return new OpenAI(
            key: Config::get('katalysis.ai.open_ai_key'),
            model: Config::get('katalysis.ai.open_ai_model')
        );
    }

    protected function embeddings(): EmbeddingsProviderInterface
    {
        return new OpenAIEmbeddingsProvider(
            key: Config::get('katalysis.ai.open_ai_key'),
            model: 'text-embedding-3-small'
        );
    }
    
    protected function vectorStore(): VectorStoreInterface
    {
        return new FileVectorStore(
            directory: DIR_APPLICATION . '/files/neuron',
            topK: 8  // Increased from 4 to 8 for better search results
        );
    }





    protected function chatHistory(): \NeuronAI\Chat\History\AbstractChatHistory
    {
        return new FileChatHistory(
            directory: DIR_APPLICATION . '/files/neuron',
            key: '1', // The key allow to store different files to separate conversations
            contextWindow: 50000
        );
    }



    public function instructions(): string
    {
        return new SystemPrompt(
            background: [
                "You are an expert AI assistant for Katalysis, specializing in web design, development and digital marketing.",
                "You have access to indexed content from the Katalysis website and should use this information to provide accurate, contextual responses and encourage users to make contact with Katalysis.",
                "",
                "IMPORTANT GUIDELINES:",
                "1. ALWAYS use the provided context information when available to answer questions",
                "2. If the context contains relevant information, base your response primarily on that content",
                "3. If the context doesn't contain relevant information, clearly state this and provide a general helpful response",
                "4. Be specific and reference details from the context when possible",
                "5. Maintain a professional, helpful tone",
                "6. If you're unsure about something, acknowledge the limitations",
                "7. Format responses in markdown when appropriate",
                "8. Keep responses concise but informative",
                "",
                "CONTEXT USAGE:",
                "- The context provided contains indexed content from the Katalysis website",
                "- Use this content to provide accurate, up-to-date information about Katalysis services",
                "- Reference specific details, services, or information from the context",
                "- If the context is empty or irrelevant, provide a general helpful response"
            ]
        );
    }

    

}   
