<?php   
namespace Concrete\Package\KatalysisAi\Controller\SinglePage\Dashboard\System\Basics;

use Core;
use Config;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\User\User;
use KatalysisAi\AiAgent;
use \NeuronAI\Chat\Messages\UserMessage;

use Concrete\Core\Http\Response;
use Concrete\Core\Http\ResponseFactory;
use Concrete\Core\Http\Request;
use KatalysisAi\RagAgent;
use Symfony\Component\HttpFoundation\JsonResponse;

class Ai extends DashboardPageController
{

    public function view()
    {
        $this->requireAsset('css', 'katalysis-ai');
        $this->requireAsset('javascript', 'katalysis-ai');

        $this->set('title', t('AI Settings'));
        $this->set('token', $this->app->make('token'));
        $this->set('form', $this->app->make('helper/form'));

        $config = $this->app->make('config');
        $this->set('open_ai_key', (string) $config->get('katalysis.ai.open_ai_key'));
        $this->set('open_ai_model', (string) $config->get('katalysis.ai.open_ai_model'));
        $this->set('anthropic_key', (string) $config->get('katalysis.ai.anthropic_key'));
        $this->set('anthropic_model', (string) $config->get('katalysis.ai.anthropic_model'));
        $this->set('ollama_url', (string) $config->get('katalysis.ai.ollama_url'));
        $this->set('ollama_model', (string) $config->get('katalysis.ai.ollama_model'));

        $this->set('results', []);
    }

    public function save() 
	{
		if (!$this->token->validate('ai.settings')) {
            $this->error->add($this->token->getErrorMessage());
        }
        //$segmentMaxLength = (int) $this->post('segment_max_length');
        //if (!$segmentMaxLength) {
        //    $this->error->add(t('Please input segment length.'));
        //}

        if (!$this->error->has()) {
            $config = $this->app->make('config');
            $config->save('katalysis.ai.open_ai_key', (string) $this->post('open_ai_key'));
            $config->save('katalysis.ai.open_ai_model', (string) $this->post('open_ai_model'));
            $config->save('katalysis.ai.anthropic_key', (string) $this->post('anthropic_key'));
            $config->save('katalysis.ai.anthropic_model', (string) $this->post('anthropic_model'));
            $config->save('katalysis.ai.ollama_url', (string) $this->post('ollama_url'));
            $config->save('katalysis.ai.ollama_model', (string) $this->post('ollama_model'));
            $this->flash('success', t('AI settings have been updated.'));
        }
        return $this->buildRedirect($this->action());
    }





    /**
     * Force the AI response into the correct one-sentence UK spelling format
     */
    private function forceResponseFormat(string $response, string $originalQuestion): string
    {
        // Extract the service name from the original question
        $serviceName = $this->extractServiceName($originalQuestion);
        
        // Convert to UK spelling
        $serviceName = $this->convertToUKSpelling($serviceName);
        
        // Create the forced response format
        return "Yes, we {$serviceName}. Contact us for more information.";
    }
    
    /**
     * Extract service name from the question
     */
    private function extractServiceName(string $question): string
    {
        $question = strtolower($question);
        
        // Common service patterns
        if (strpos($question, 'concrete cms design') !== false) {
            return 'offer Concrete CMS design services';
        }
        if (strpos($question, 'concrete cms') !== false && strpos($question, 'host') !== false) {
            return 'specialise in Concrete CMS hosting';
        }
        if (strpos($question, 'web design') !== false) {
            return 'offer web design services';
        }
        if (strpos($question, 'web development') !== false) {
            return 'offer web development services';
        }
        if (strpos($question, 'hosting') !== false) {
            return 'offer hosting services';
        }
        if (strpos($question, 'support') !== false) {
            return 'offer support services';
        }
        
        // Default fallback
        return 'provide the services you need';
    }
    
    /**
     * Convert common words to UK spelling
     */
    private function convertToUKSpelling(string $text): string
    {
        $replacements = [
            'specialize' => 'specialise',
            'organization' => 'organisation',
            'customize' => 'customise',
            'optimize' => 'optimise',
            'realize' => 'realise',
            'analyze' => 'analyse',
            'color' => 'colour',
            'favor' => 'favour',
            'labor' => 'labour',
            'center' => 'centre',
            'meter' => 'metre',
            'theater' => 'theatre'
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Clear chat history files from the server
     */
    public function clear_chat_history()
    {
        try {
            $chatDirectory = DIR_APPLICATION . '/files/neuron';
            
            // Clear RAG chat history (key '1')
            $ragChatFile = $chatDirectory . '/1.json';
            if (file_exists($ragChatFile)) {
                unlink($ragChatFile);
            }
            
            // Clear basic AI chat history (key '2')
            $basicChatFile = $chatDirectory . '/2.json';
            if (file_exists($basicChatFile)) {
                unlink($basicChatFile);
            }
            
            // Also clear any other chat files that might exist
            $chatFiles = glob($chatDirectory . '/*.json');
            foreach ($chatFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            
            return new JsonResponse(['success' => true, 'message' => 'Chat history cleared successfully']);
            
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }


    public function ask_ai()
    {
        // Initialize variables
        $message = null;
        $mode = 'rag'; // Default to RAG mode

        // Get the request object
        $request = $this->app->make('request');
        
        // Check if this is a JSON request
        $contentType = $request->headers->get('Content-Type');
        $rawContent = $request->getContent();
        
        // Check if content looks like JSON (starts with { or [)
        if (strpos($contentType, 'application/json') !== false || 
            (trim($rawContent) && (strpos(trim($rawContent), '{') === 0 || strpos(trim($rawContent), '[') === 0))) {
            // Handle JSON request
            $jsonData = json_decode($rawContent, true);
            $message = $jsonData['message'] ?? null;
            $mode = $jsonData['mode'] ?? 'rag';
        } else {
            // Handle form data request
            $data = $request->request->all();
            $message = $data['message'] ?? null;
            $mode = $data['mode'] ?? 'rag';
        }

        // Get AI configuration
        $config = $this->app->make('config');
        $openaiKey = $config->get('katalysis.ai.open_ai_key');
        $openaiModel = $config->get('katalysis.ai.open_ai_model');
        $linkQualityThreshold = (float) $config->get('katalysis.ai.link_quality_threshold', 0.5);
        $maxLinksPerResponse = (int) $config->get('katalysis.ai.max_links_per_response', 3);


        if (!isset($message) || empty($message)) {
            $message = 'Please apologise for not understanding the question';
        }

        try {
            // Test if configuration is valid
            if (empty($openaiKey) || empty($openaiModel)) {
                return new JsonResponse(
                    ['error' => 'AI configuration is incomplete. Please check your OpenAI API key and model settings.'], 
                    400
                );
            }

            if ($mode === 'rag') {
                // RAG Mode: Use RagAgent with its instructions
                $ragAgent = new RagAgent();
                
                // Get relevant documents with metadata for potential "more info" links
                $relevantDocs = $ragAgent->getRelevantDocumentsWithMetadata($message, 20);
                
                // Use structured response for consistent formatting
                try {
                    $structuredResponse = $ragAgent->getStructuredResponse($message);
                    $responseContent = $structuredResponse->response;
                } catch (\Exception $e) {
                    // Fallback to regular response
                    $response = $ragAgent->answer(new UserMessage($message));
                    $responseContent = $response->getContent();
                }
                
                // Note: Post-processing removed to test new model
                
                // Extract metadata for "more info" links with improved scoring
                $metadata = [];
                $seenUrls = []; // Track seen URLs to avoid duplicates
                
                foreach ($relevantDocs as $doc) {
                    if (isset($doc->metadata['url']) && !empty($doc->metadata['url'])) {
                        $url = $doc->metadata['url'];
                        $score = $doc->score ?? 0;
                        $title = $doc->sourceName ?? '';
                        $content = $doc->content ?? '';
                        
                        // Skip if we've already seen this URL
                        if (in_array($url, $seenUrls)) {
                            continue;
                        }
                        
                        // Skip local/geographic pages that aren't relevant to most users
                        $titleLower = strtolower($title);
                        $localKeywords = ['harpenden', 'st albans', 'hertfordshire', 'bedfordshire', 'luton', 'watford'];
                        $isLocalPage = false;
                        foreach ($localKeywords as $localKeyword) {
                            if (strpos($titleLower, $localKeyword) !== false) {
                                $isLocalPage = true;
                                break;
                            }
                        }
                        
                        if ($isLocalPage) {
                            continue;
                        }
                        
                        // Boost score for exact title matches
                        $boostedScore = $score;
                        $queryLower = strtolower($message);
                        $titleLower = strtolower($title);
                        
                        // Major boost for exact title matches
                        if (strpos($titleLower, $queryLower) !== false || strpos($queryLower, $titleLower) !== false) {
                            $boostedScore += 0.5; // Increased from 0.3
                        }
                        
                        // Boost for partial title matches
                        $queryWords = explode(' ', $queryLower);
                        $titleWords = explode(' ', $titleLower);
                        $matchingWords = array_intersect($queryWords, $titleWords);
                        if (count($matchingWords) > 0) {
                            $boostedScore += (count($matchingWords) / count($queryWords)) * 0.4; // Increased from 0.2
                        }
                        
                        // Enhanced service keyword matching with synonyms
                        $serviceKeywords = [
                            'hosting' => ['host', 'hosted', 'server', 'deploy', 'deployment'],
                            'support' => ['help', 'assist', 'maintain', 'maintenance'],
                            'development' => ['develop', 'build', 'create', 'programming'],
                            'design' => ['designing', 'layout', 'ui', 'ux'],
                            'cms' => ['content management', 'content management system'],
                            'concrete' => ['concrete cms', 'concrete5']
                        ];
                        
                        foreach ($serviceKeywords as $keyword => $synonyms) {
                            $keywordFound = false;
                            
                            // Check if the keyword is in the title
                            if (strpos($titleLower, $keyword) !== false) {
                                $keywordFound = true;
                            }
                            
                            // Check if any synonyms are in the title
                            foreach ($synonyms as $synonym) {
                                if (strpos($titleLower, $synonym) !== false) {
                                    $keywordFound = true;
                                    break;
                                }
                            }
                            
                            // If keyword found in title, check if query contains related terms
                            if ($keywordFound) {
                                $queryHasKeyword = false;
                                
                                // Check if query contains the keyword
                                if (strpos($queryLower, $keyword) !== false) {
                                    $queryHasKeyword = true;
                                }
                                
                                // Check if query contains any synonyms
                                foreach ($synonyms as $synonym) {
                                    if (strpos($queryLower, $synonym) !== false) {
                                        $queryHasKeyword = true;
                                        break;
                                    }
                                }
                                
                                if ($queryHasKeyword) {
                                    $boostedScore += 0.3; // Increased from 0.15
                                }
                            }
                        }
                        
                        // Special boost for hosting-related queries
                        if (strpos($queryLower, 'host') !== false && strpos($titleLower, 'hosting') !== false) {
                            $boostedScore += 0.6; // Increased from 0.25 - major boost for host/hosting
                        }
                        
                        // Special boost for any hosting-related pages when query mentions hosting
                        if (strpos($queryLower, 'host') !== false && 
                            (strpos($titleLower, 'hosting') !== false || strpos($titleLower, 'dedicated') !== false)) {
                            $boostedScore += 0.5; // Boost for any hosting-related content
                        }
                        
                        // Special boost for CMS-related queries
                        if ((strpos($queryLower, 'cms') !== false || strpos($queryLower, 'concrete') !== false) && 
                            strpos($titleLower, 'cms') !== false) {
                            $boostedScore += 0.4; // Increased from 0.2
                        }
                        
                        // Only include documents with good relevance scores (using boosted score)
                        if ($boostedScore >= $linkQualityThreshold) {
                            $metadata[] = [
                                'title' => $title,
                                'url' => $url,
                                'score' => $boostedScore,
                                'original_score' => $score,
                                'boosting_details' => [
                                    'query' => $message,
                                    'title' => $title,
                                    'original_score' => $score,
                                    'boosted_score' => $boostedScore,
                                    'boost_amount' => $boostedScore - $score
                                ]
                            ];
                            $seenUrls[] = $url;
                        }
                    }
                }
                
                // Sort by boosted relevance score (highest first) and limit to max links
                usort($metadata, function($a, $b) {
                    return $b['score'] <=> $a['score'];
                });
                
                $metadata = array_slice($metadata, 0, $maxLinksPerResponse);
                
                // Fallback: if no links meet the quality threshold, try with a lower threshold
                if (empty($metadata) && $linkQualityThreshold > 0.3) {
                    $fallbackThreshold = 0.3;
                    $metadata = [];
                    $seenUrls = [];
                    
                    foreach ($relevantDocs as $doc) {
                        if (isset($doc->metadata['url']) && !empty($doc->metadata['url'])) {
                            $url = $doc->metadata['url'];
                            $score = $doc->score ?? 0;
                            $title = $doc->sourceName ?? '';
                            
                            if (!in_array($url, $seenUrls)) {
                                // Apply same boosting logic for fallback
                                $boostedScore = $score;
                                $queryLower = strtolower($message);
                                $titleLower = strtolower($title);
                                
                                // Skip local/geographic pages that aren't relevant to most users
                                $localKeywords = ['harpenden', 'st albans', 'hertfordshire', 'bedfordshire', 'luton', 'watford'];
                                $isLocalPage = false;
                                foreach ($localKeywords as $localKeyword) {
                                    if (strpos($titleLower, $localKeyword) !== false) {
                                        $isLocalPage = true;
                                        break;
                                    }
                                }
                                
                                if ($isLocalPage) {
                                    continue;
                                }
                                
                                // Major boost for exact title matches
                                if (strpos($titleLower, $queryLower) !== false || strpos($queryLower, $titleLower) !== false) {
                                    $boostedScore += 0.5; // Increased from 0.3
                                }
                                
                                // Boost for partial title matches
                                $queryWords = explode(' ', $queryLower);
                                $titleWords = explode(' ', $titleLower);
                                $matchingWords = array_intersect($queryWords, $titleWords);
                                if (count($matchingWords) > 0) {
                                    $boostedScore += (count($matchingWords) / count($queryWords)) * 0.4; // Increased from 0.2
                                }
                                
                                // Enhanced service keyword matching with synonyms
                                $serviceKeywords = [
                                    'hosting' => ['host', 'hosted', 'server', 'deploy', 'deployment'],
                                    'support' => ['help', 'assist', 'maintain', 'maintenance'],
                                    'development' => ['develop', 'build', 'create', 'programming'],
                                    'design' => ['designing', 'layout', 'ui', 'ux'],
                                    'cms' => ['content management', 'content management system'],
                                    'concrete' => ['concrete cms', 'concrete5']
                                ];
                                
                                foreach ($serviceKeywords as $keyword => $synonyms) {
                                    $keywordFound = false;
                                    
                                    // Check if the keyword is in the title
                                    if (strpos($titleLower, $keyword) !== false) {
                                        $keywordFound = true;
                                    }
                                    
                                    // Check if any synonyms are in the title
                                    foreach ($synonyms as $synonym) {
                                        if (strpos($titleLower, $synonym) !== false) {
                                            $keywordFound = true;
                                            break;
                                        }
                                    }
                                    
                                    // If keyword found in title, check if query contains related terms
                                    if ($keywordFound) {
                                        $queryHasKeyword = false;
                                        
                                        // Check if query contains the keyword
                                        if (strpos($queryLower, $keyword) !== false) {
                                            $queryHasKeyword = true;
                                        }
                                        
                                        // Check if query contains any synonyms
                                        foreach ($synonyms as $synonym) {
                                            if (strpos($queryLower, $synonym) !== false) {
                                                $queryHasKeyword = true;
                                                break;
                                            }
                                        }
                                        
                                        if ($queryHasKeyword) {
                                            $boostedScore += 0.3; // Increased from 0.15
                                        }
                                    }
                                }
                                
                                // Special boost for hosting-related queries
                                if (strpos($queryLower, 'host') !== false && strpos($titleLower, 'hosting') !== false) {
                                    $boostedScore += 0.6; // Increased from 0.25 - major boost for host/hosting
                                }
                                
                                // Special boost for any hosting-related pages when query mentions hosting
                                if (strpos($queryLower, 'host') !== false && 
                                    (strpos($titleLower, 'hosting') !== false || strpos($titleLower, 'dedicated') !== false)) {
                                    $boostedScore += 0.5; // Boost for any hosting-related content
                                }
                                
                                // Special boost for CMS-related queries
                                if ((strpos($queryLower, 'cms') !== false || strpos($queryLower, 'concrete') !== false) && 
                                    strpos($titleLower, 'cms') !== false) {
                                    $boostedScore += 0.4; // Increased from 0.2
                                }
                                
                                if ($boostedScore >= $fallbackThreshold) {
                                    $metadata[] = [
                                        'title' => $title,
                                        'url' => $url,
                                        'score' => $boostedScore,
                                        'original_score' => $score
                                    ];
                                    $seenUrls[] = $url;
                                }
                            }
                        }
                    }
                    
                    usort($metadata, function($a, $b) {
                        return $b['score'] <=> $a['score'];
                    });
                    
                    $metadata = array_slice($metadata, 0, $maxLinksPerResponse);
                }
                
                // Return response with metadata
                return new JsonResponse([
                    'content' => $responseContent,
                    'metadata' => $metadata
                ]);

            } else {
                // Basic Mode: Use regular AiAgent
                $agent = new AiAgent();
                $response = $agent->chat(
                    new UserMessage($message)
                );
                
                $responseContent = $response->getContent();
                
                return new JsonResponse([
                    'content' => $responseContent,
                    'metadata' => []
                ]);
            }

        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Failed to process request: ' . $e->getMessage()], 
                500
            );
        }
    }



    

}