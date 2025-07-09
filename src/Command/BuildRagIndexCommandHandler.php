<?php
namespace KatalysisAi\Command;

defined('C5_EXECUTE') or die("Access Denied.");

use Loader;
use KatalysisAi\RagBuildIndex;
use Concrete\Core\Support\Facade\Log;

class BuildRagIndexCommandHandler
{

    public function __invoke(BuildRagIndexCommand $command)
    {
        try {
            Log::addInfo('Starting automated RAG index rebuild...');
            
            $ragBuildIndex = new RagBuildIndex();
            
            // Clear existing index first
            Log::addInfo('Clearing existing index...');
            $ragBuildIndex->clearIndex();
            
            // Build new index
            Log::addInfo('Building new index...');
            $results = $ragBuildIndex->buildIndex();
            $ragBuildIndex->addDocuments($results);
            
            Log::addInfo('RAG index rebuild completed successfully. Indexed ' . count($results) . ' pages.');
            
            return true;
            
        } catch (\Exception $e) {
            Log::addError('RAG index rebuild failed: ' . $e->getMessage());
            throw $e;
        }
    }

}
