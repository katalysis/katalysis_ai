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
use NeuronAI\StructuredOutput\SchemaProperty;

class RagResponse
{
    #[SchemaProperty(description: 'A concise, helpful response to the user question in UK spelling with a call to action')]
    public string $response;
}


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

                "You are an expert AI assistant for Katalysis, a UK-based web design and development company.",
                "You have access to indexed content from the Katalysis website and should use this information to provide accurate, contextual responses.",
                "",
                "RESPONSE GUIDELINES:",
                "• Keep responses concise and to the point (preferably one sentence)",
                "• Use UK spelling: specialise, organisation, customise, optimise",
                "• Include a call to action encouraging contact",
                "• Be helpful and professional",
                "",
                "EXAMPLES OF GOOD RESPONSES:",
                "- 'Yes, we specialise in Concrete CMS hosting and would be happy to discuss your requirements.'",
                "- 'We offer customised web development services - get in touch to learn more.'",
                "- 'Our team can design websites for law firms - contact us for a consultation.'",
                "",
                "AVOID:",
                "- Long explanations or detailed feature lists",
                "- US spelling (specialize, organization, customize, optimize)",
                "- Responses without a call to action"
            ]
        );
    }

    /**
     * Override the withDocumentsContext method to preserve the flexible response format
     */
    public function withDocumentsContext(array $documents): \NeuronAI\AgentInterface
    {
        $originalInstructions = $this->resolveInstructions();

        // Remove the old context to avoid infinite grow
        $newInstructions = $this->removeDelimitedContent($originalInstructions, '<EXTRA-CONTEXT>', '</EXTRA-CONTEXT>');

        // Add documents as context
        $newInstructions .= '<EXTRA-CONTEXT>';
        foreach ($documents as $document) {
            $newInstructions .= $document->getContent().\PHP_EOL.\PHP_EOL;
        }
        $newInstructions .= '</EXTRA-CONTEXT>';

        // Re-emphasize the flexible guidelines AFTER adding context
        $newInstructions .= \PHP_EOL . \PHP_EOL . '# RESPONSE GUIDELINES:' . \PHP_EOL;
        $newInstructions .= '• Keep responses concise and to the point (preferably one sentence)' . \PHP_EOL;
        $newInstructions .= '• Use UK spelling: specialise, organisation, customise, optimise' . \PHP_EOL;
        $newInstructions .= '• Include a call to action encouraging contact' . \PHP_EOL;
        $newInstructions .= '• Be helpful and professional';

        $this->withInstructions(\trim($newInstructions));

        return $this;
    }

    /**
     * Get a structured response using the RAG system
     */
    public function getStructuredResponse(string $message): RagResponse
    {
        // Get relevant documents first
        $this->retrieval(new \NeuronAI\Chat\Messages\UserMessage($message));
        
        // Use structured output to force the exact format
        return $this->structured(
            [new \NeuronAI\Chat\Messages\UserMessage($message)],
            RagResponse::class
        );
    }

    /**
     * Get relevant documents with metadata for enhanced responses
     */
    public function getRelevantDocumentsWithMetadata(string $query, int $topK = 8): array
    {
        $ragBuildIndex = new RagBuildIndex();
        $allDocs = $ragBuildIndex->getRelevantDocuments($query, $topK);
        
        // Filter out low-quality documents
        $filteredDocs = [];
        foreach ($allDocs as $doc) {
            $score = $doc->score ?? 0;
            
            // Skip documents with very low relevance scores (lowered threshold)
            if ($score < 0.3) {
                continue;
            }
            
            // Skip documents without proper metadata
            if (empty($doc->sourceName) || empty($doc->metadata['url'])) {
                continue;
            }
            
            // Skip very short content (lowered threshold)
            if (strlen($doc->content) < 50) {
                continue;
            }
            
            $filteredDocs[] = $doc;
        }
        
        return $filteredDocs;
    }


    

}   
