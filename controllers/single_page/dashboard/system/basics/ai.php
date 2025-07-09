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
                
                // Use the base RAG class's answer method which handles retrieval automatically
                $response = $ragAgent->answer(
                    new UserMessage($message)
                );
            } else {
                // Basic Mode: Use regular AiAgent
                $agent = new AiAgent();
                $response = $agent->chat(
                    new UserMessage($message)
                );
            }
            
            $responseContent = $response->getContent();
            
            return new JsonResponse($responseContent);
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Failed to process request: ' . $e->getMessage()], 
                500
            );
        }
    }



    

}