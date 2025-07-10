<?php

namespace KatalysisAi;

use Config;
use \NeuronAI\Agent;
use \NeuronAI\SystemPrompt;
use \NeuronAI\Chat\History\FileChatHistory;



use \NeuronAI\Chat\Messages\UserMessage;
use \NeuronAI\Providers\AIProviderInterface;
use \NeuronAI\Providers\OpenAI\OpenAI;
use \NeuronAI\Observability\AgentMonitoring;


class AiAgent extends Agent
{
    
    protected function provider(): AIProviderInterface
    {
        // return an AI provider (Anthropic, OpenAI, Ollama, Gemini, etc.)
        return new OpenAI(
            key: Config::get('katalysis.ai.open_ai_key'),
            model: Config::get('katalysis.ai.open_ai_model')
        );
    }

    protected function chatHistory(): \NeuronAI\Chat\History\AbstractChatHistory
    {
        return new FileChatHistory(
            directory: '/var/www/vhosts/dev35.katalysis.net/httpdocs/application/files/neuron',
            key: '2', // The key allow to store different files to separate conversations
            contextWindow: 50000
        );
    }



    public function instructions(): string
    {
        return new SystemPrompt(
            background: [
                "You are an expert in the Concrete CMS content management system. Deliver responses in markdown format."
            ]
        );
    }

    

}   
