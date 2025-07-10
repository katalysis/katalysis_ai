<?php
namespace KatalysisAi;

use CollectionAttributeKey;
use Concrete\Core\Attribute\Key\CollectionKey;
use Concrete\Core\Block\BlockController;
use Concrete\Core\Feature\Features;
use Concrete\Core\Feature\UsesFeatureInterface;
use Concrete\Core\Page\PageList;
use Concrete\Core\Support\Facade\Core;
use Page;

use NeuronAI\RAG\DataLoader\StringDataLoader;
use NeuronAI\RAG\Embeddings\OpenAIEmbeddingsProvider;
use NeuronAI\RAG\VectorStore\FileVectorStore;
use Concrete\Core\Support\Facade\Config;

class RagBuildIndex {

    public function clearIndex(): void
    {
        // Use the correct path for your environment
        $storeFile = DIR_APPLICATION . '/files/neuron/neuron.store';
        
        if (file_exists($storeFile)) {
            unlink($storeFile);
        }
    }

    public function buildIndex()
    {
        $ipl = new PageList();
        $ipl->setSiteTreeToAll();
        
        $pages = [];
        $results = $ipl->getResults();

        foreach ($results as $r) {
            $content = $r->getPageIndexContent();
            
            // Skip pages with empty content
            if (empty($content) || !is_string($content)) {
                continue;
            }
            
            // Skip pages that are too short (likely system pages)
            if (strlen($content) < 50) {
                continue;
            }

            $pages[] = [
                'title' => $r->getCollectionName(),
                'description' => $r->getCollectionDescription(),
                'content' => $content,
                'link' => $r->getCollectionLink()
            ];
        }
        return $pages;
    }

    public function addDocuments(array $pages): void
    {
        $embeddingProvider = new OpenAIEmbeddingsProvider(
            key: Config::get('katalysis.ai.open_ai_key'),
            model: 'text-embedding-3-small'
        );
        $vectorStore = new FileVectorStore(
            directory: DIR_APPLICATION . '/files/neuron',
            topK: 4
        );
        
        $processedCount = 0;
        $skippedCount = 0;
        
        foreach ($pages as $page) {
            // Validate content before processing
            if (empty($page['content']) || !is_string($page['content'])) {
                $skippedCount++;
                continue;
            }
            
            try {
                $documents = StringDataLoader::for($page['content'])->getDocuments();
                
                foreach ($documents as $document) {
                    if (!($document instanceof \NeuronAI\RAG\Document)) {
                        throw new \RuntimeException('Expected Document, got: ' . (is_object($document) ? get_class($document) : gettype($document)));
                    }
                    
                    // Add page metadata to document
                    $document->sourceName = $page['title'];
                    $document->sourceType = 'page';
                    $document->addMetadata('url', $page['link']); // Store the page URL in metadata
                    
                    $document->embedding = $embeddingProvider->embedText($document->content);
                    $vectorStore->addDocument($document);
                    $processedCount++;
                }
            } catch (\Exception $e) {
                $skippedCount++;
            }
        }
    }

    public function getRelevantDocuments(string $query, int $topK = 12): array
    {
        $embeddingProvider = new OpenAIEmbeddingsProvider(
            key: Config::get('katalysis.ai.open_ai_key'),
            model: 'text-embedding-3-small'
        );
        $vectorStore = new FileVectorStore(
            directory: DIR_APPLICATION . '/files/neuron',
            topK: $topK
        );
        $queryEmbedding = $embeddingProvider->embedText($query);
        
        return $vectorStore->similaritySearch($queryEmbedding);
    }
}
