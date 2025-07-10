<?php
defined('C5_EXECUTE') or die('Access Denied.');

use \NeuronAI\Chat\Messages\UserMessage;
use \NeuronAI\Observability\AgentMonitoring;
use KatalysisAi\AiAgent;
use Concrete\Core\Support\Facade\Package;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Page\Controller\DashboardPageController;
use KatalysisAi\RagAgent;

$token = \Core::make('token');

/**
 * @var Packages\KatalysisAi\Controller\SinglePage\Dashboard\System\Basics\KatalysisAi $controller
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var int $segmentMaxLength
 */


$app = Application::getFacadeApplication();
$form = $app->make('helper/form');
?>

<form method="post" enctype="multipart/form-data" action="<?= $controller->action('save') ?>">
    <?php $token->output('ai.settings'); ?>
    <div id="ccm-dashboard-content-inner">

        <script type="module" src="/packages/katalysis_ai/js/scrolly-rail.js"></script>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">AI Mode</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="ragModeToggle" checked>
                            <label class="form-check-label" for="ragModeToggle">
                                <strong>RAG Mode</strong> - Uses indexed content for context-aware responses
                            </label>
                        </div>
                        <small class="text-muted">
                            <span id="modeDescription">RAG Mode: AI will search your indexed content to provide relevant
                                answers.</span>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-12 col-md-8 col-lg-6">
                <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
                <script>
                    function renderMarkdown(markdown) {
                        return marked.parse(markdown);
                    }
                </script>
                <script>
                    console.log('=== CHAT SCRIPT LOADED ===');
                    console.log('Current time:', new Date().toISOString());

                    // Initialize currentMode variable
                    let currentMode = 'rag'; // Default to RAG mode

                    // Mode toggle functionality
                    document.addEventListener('DOMContentLoaded', function() {
                        const ragModeToggle = document.getElementById('ragModeToggle');
                        const modeDescription = document.getElementById('modeDescription');
                        
                        if (ragModeToggle) {
                            // Set initial state
                            currentMode = ragModeToggle.checked ? 'rag' : 'basic';
                            updateModeDescription();
                            
                            // Add event listener
                            ragModeToggle.addEventListener('change', function() {
                                currentMode = this.checked ? 'rag' : 'basic';
                                updateModeDescription();
                                console.log('Mode changed to:', currentMode);
                            });
                        }
                        
                        function updateModeDescription() {
                            if (modeDescription) {
                                if (currentMode === 'rag') {
                                    modeDescription.textContent = 'RAG Mode: AI will search your indexed content to provide relevant answers.';
                                } else {
                                    modeDescription.textContent = 'Basic Mode: AI will provide general responses without searching indexed content.';
                                }
                            }
                        }
                    });

                    // Chat persistence functions
                    function saveChatHistory() {
                        console.log('Saving chat history...');
                        
                        const chatContainer = document.getElementById('chat');
                        if (!chatContainer) {
                            console.error('Chat container not found!');
                            return;
                        }
                        
                        const chatHistory = chatContainer.innerHTML;
                        console.log('Chat history length:', chatHistory.length);
                        
                        try {
                            localStorage.setItem('katalysis_chat_history', chatHistory);
                            localStorage.setItem('katalysis_chat_timestamp', Date.now().toString());
                            console.log('Chat history saved successfully');
                        } catch (e) {
                            console.error('Error saving chat history:', e);
                        }
                    }

                    function loadChatHistory() {
                        console.log('Loading chat history...');
                        
                        const chatContainer = document.getElementById('chat');
                        if (!chatContainer) {
                            console.error('Chat container not found!');
                            return;
                        }
                        
                        try {
                            const savedHistory = localStorage.getItem('katalysis_chat_history');
                            const timestamp = localStorage.getItem('katalysis_chat_timestamp');
                            
                            console.log('Saved history exists:', !!savedHistory);
                            console.log('Timestamp exists:', !!timestamp);
                            
                            if (savedHistory && timestamp) {
                                const age = Date.now() - parseInt(timestamp);
                                const maxAge = 24 * 60 * 60 * 1000; // 24 hours
                                
                                console.log('Chat age:', age, 'ms');
                                
                                if (age < maxAge) {
                                    console.log('Loading saved chat history...');
                                    
                                    // Replace the entire chat container content
                                    chatContainer.innerHTML = savedHistory;
                                    
                                    console.log('Chat history loaded successfully');
                                    
                                    // Scroll to bottom after loading
                                    setTimeout(function() {
                                        scrollToBottom();
                                    }, 100);
                                } else {
                                    console.log('Chat history is too old, clearing...');
                                    clearChatHistory();
                                }
                            } else {
                                console.log('No saved chat history found');
                            }
                        } catch (e) {
                            console.error('Error loading chat history:', e);
                        }
                    }

                    function clearChatHistory() {
                        console.log('Clearing chat history...');
                        
                        // Clear browser localStorage
                        localStorage.removeItem('katalysis_chat_history');
                        localStorage.removeItem('katalysis_chat_timestamp');
                        
                        // Clear server-side chat files
                        $.ajax({
                            type: "POST",
                            url: "<?= $controller->action('clear_chat_history') ?>",
                            headers: {
                                'X-CSRF-TOKEN': '<?= $token->generate('ai.settings') ?>'
                            },
                            success: function(data) {
                                console.log('Server chat history cleared:', data);
                                location.reload();
                            },
                            error: function(xhr, status, error) {
                                console.error('Error clearing server chat history:', error);
                                // Still reload even if server clear fails
                                location.reload();
                            }
                        });
                    }

                    function scrollToBottom() {
                        const chatContainer = document.getElementById('chat');
                        if (chatContainer) {
                            console.log('Scrolling to bottom...');
                            console.log('Scroll height:', chatContainer.scrollHeight);
                            console.log('Client height:', chatContainer.clientHeight);
                            
                            // Force scroll to bottom
                            chatContainer.scrollTop = chatContainer.scrollHeight;
                            
                            // Also try with a small delay
                            setTimeout(function() {
                                chatContainer.scrollTop = chatContainer.scrollHeight;
                            }, 50);
                        }
                    }

                    // Load chat history when page loads
                    $(document).ready(function() {
                        console.log('jQuery ready - loading chat history...');
                        loadChatHistory();
                    });

                    // Also try loading with vanilla JS
                    document.addEventListener('DOMContentLoaded', function() {
                        console.log('DOM loaded - loading chat history...');
                        loadChatHistory();
                    });

                    // Your existing addMessage function
                    function addMessage() {
                        var messageValue = document.getElementById('message').value;
                        if (!messageValue.trim()) {
                            alert('Please enter a message');
                            return;
                        } else {
                            $("#chat").append('<div class="user-message">' + messageValue + '</div>');
                            saveChatHistory(); // Save after user message
                            scrollToBottom();
                        }
                        
                        $("#chat").append('<div class="ai-loading">AI is thinking...</div>');
                        saveChatHistory(); // Save after loading indicator
                        scrollToBottom();
                        
                        $.ajax({
                            type: "POST",
                            url: "<?= $controller->action('ask_ai') ?>",
                            data: JSON.stringify({
                                message: messageValue,
                                mode: currentMode
                            }),
                            contentType: "application/json",
                            headers: {
                                'X-CSRF-TOKEN': '<?= $token->generate('ai.settings') ?>'
                            },
                            success: function(data) {
                                console.log('Response:', data);
                                $(".ai-loading").remove();
                                
                                // Handle new response format with metadata
                                let responseContent = data;
                                let metadata = [];
                                
                                if (typeof data === 'object' && data.content) {
                                    responseContent = data.content;
                                    metadata = data.metadata || [];
                                }
                                
                                let responseHtml = '<div class="ai-response"><img src="https://d7keiwzj12p9.cloudfront.net/avatars/katalysis-bot-icon-1748356162310.webp" alt="Katalysis Bot"><div>' + renderMarkdown(responseContent);
                                
                                // Add "More Info" links if metadata is available
                                if (metadata && metadata.length > 0) {
                                    responseHtml += '<div class="more-info-links mt-3"><strong>More Information:</strong><ul class="list-unstyled mt-2">';
                                    metadata.forEach(function(link) {
                                        responseHtml += '<li><a href="' + link.url + '" target="_blank" class="btn btn-sm btn-outline-primary me-2 mb-1">' + link.title + '</a></li>';
                                    });
                                    responseHtml += '</ul></div>';
                                }
                                
                                responseHtml += '</div></div>';
                                $("#chat").append(responseHtml);
                                saveChatHistory(); // Save after AI response
                                scrollToBottom();
                                document.getElementById('message').value = '';
                            },
                            error: function(xhr, status, error) {
                                console.error('Error:', error);
                                $(".ai-loading").remove();
                                $("#chat").append('<div class="ai-error">Error: ' + error + '</div>');
                                saveChatHistory(); // Save after error
                                scrollToBottom();
                            }
                        });
                    }

                    // Update the addMessageWithMode function as well
                    function addMessageWithMode(message) {
                        var messageValue = message || document.getElementById('message').value;
                        if (!messageValue.trim()) {
                            alert('Please enter a message');
                            return;
                        } else {
                            $("#chat").append('<div class="user-message">' + messageValue + '</div>');
                            saveChatHistory(); // Save after each message
                            scrollToBottom(); // Scroll after adding user message
                        }

                        $("#chat").append('<div class="ai-loading">AI is thinking...</div>');
                        saveChatHistory(); // Save after adding loading indicator

                        $.ajax({
                            type: "POST",
                            url: "<?= $controller->action('ask_ai') ?>",
                            data: JSON.stringify({
                                message: messageValue,
                                mode: currentMode
                            }),
                            contentType: "application/json",
                            headers: {
                                'X-CSRF-TOKEN': '<?= $token->generate('ai.settings') ?>'
                            },
                            success: function (data) {
                                console.log('Response:', data);
                                $(".ai-loading").remove();
                                
                                // Handle new response format with metadata
                                let responseContent = data;
                                let metadata = [];
                                
                                if (typeof data === 'object' && data.content) {
                                    responseContent = data.content;
                                    metadata = data.metadata || [];
                                }
                                
                                let responseHtml = '<div class="ai-response"><img src="https://d7keiwzj12p9.cloudfront.net/avatars/katalysis-bot-icon-1748356162310.webp" alt="Katalysis Bot"><div>' + renderMarkdown(responseContent);
                                
                                // Add "More Info" links if metadata is available
                                if (metadata && metadata.length > 0) {
                                    responseHtml += '<div class="more-info-links mt-3"><strong>More Information:</strong><ul class="list-unstyled mt-2">';
                                    metadata.forEach(function(link) {
                                        responseHtml += '<li><a href="' + link.url + '" target="_blank" class="btn btn-sm btn-outline-primary me-2 mb-1">' + link.title + '</a></li>';
                                    });
                                    responseHtml += '</ul></div>';
                                }
                                
                                responseHtml += '</div></div>';
                                $("#chat").append(responseHtml);
                                saveChatHistory(); // Save after AI response
                                scrollToBottom(); // Scroll after adding AI response
                                document.getElementById('message').value = '';
                            },
                            error: function (xhr, status, error) {
                                console.error('Error:', error);
                                $(".ai-loading").remove();
                                $("#chat").append('<div class="ai-error">Error: ' + error + '</div>');
                                saveChatHistory();
                                scrollToBottom(); // Scroll after adding error message
                            }
                        });
                    }

                    // Add smooth scrolling for better UX
                    function scrollToBottomSmooth() {
                        const chatContainer = document.getElementById('chat');
                        chatContainer.scrollTo({
                            top: chatContainer.scrollHeight,
                            behavior: 'smooth'
                        });
                    }

                    // Optional: Auto-scroll on window resize
                    window.addEventListener('resize', function () {
                        scrollToBottom();
                    });
                </script>
                <section>
                    <div class="card border rounded-3">
                        <div class="card-body">
                            <div id="chat" style="max-height: 400px; overflow-y: auto; padding: 15px;">
                                <div class="divider d-flex align-items-center mb-4">
                                    <p class="text-center mx-3 mb-0" style="color: #a2aab7;">Today</p>
                                </div>
                                <div class="ai-response">
                                    <img src="https://d7keiwzj12p9.cloudfront.net/avatars/katalysis-bot-icon-1748356162310.webp"
                                        alt="Katalysis Bot">
                                    <div>Hi, How can we help today?</div>
                                </div>
                            </div>
                        </div>

                        <div class="suggestions-container bg-primary-subtle d-flex align-items-center overflow-x-auto">
                            <button type="button" data-bound
                                class="bg-primary btn-scrolly-rail btn-scrolly-rail--previous animate-fade"
                                id="collection-2-btn-previous">
                                <span class="visually-hidden">Scroll previous items into view</span>
                                <i class="icon fas fa-arrow-left"></i>
                            </button>
                            <div class="scrolly-rail-wrapper">
                                <scrolly-rail data-control-previous="collection-2-btn-previous"
                                    data-control-next="collection-2-btn-next">
                                    <div class="collection-list">
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button" onclick="addMessageWithMode('Arrange a meeting')">Arrange a
                                            meeting</button>
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button" onclick="addMessageWithMode('Request a proposal')">Request a
                                            proposal</button>
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button"
                                            onclick="addMessageWithMode('Arrange a FREE Strategy Session')">Arrange a
                                            FREE Strategy Session</button>
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button"
                                            onclick="addMessageWithMode('Arrange a Pro LawSite Demo')">Arrange a Pro
                                            LawSite Demo</button>
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button" onclick="addMessageWithMode('Arrange a meeting')">Arrange a
                                            meeting</button>
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button" onclick="addMessageWithMode('Request a proposal')">Request a
                                            proposal</button>
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button"
                                            onclick="addMessageWithMode('Arrange a FREE Strategy Session')">Arrange a
                                            FREE Strategy Session</button>
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button"
                                            onclick="addMessageWithMode('Arrange a Pro LawSite Demo')">Arrange a Pro
                                            LawSite Demo</button>
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button" onclick="addMessageWithMode('Arrange a meeting')">Arrange a
                                            meeting</button>
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button" onclick="addMessageWithMode('Request a proposal')">Request a
                                            proposal</button>
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button"
                                            onclick="addMessageWithMode('Arrange a FREE Strategy Session')">Arrange a
                                            FREE Strategy Session</button>
                                        <button class="btn btn-outline-secondary btn-sm flex-shrink-0" dir="auto"
                                            type="button"
                                            onclick="addMessageWithMode('Arrange a Pro LawSite Demo')">Arrange a Pro
                                            LawSite Demo</button>
                                    </div>
                                </scrolly-rail>
                            </div>
                            <button type="button"
                                class="bg-primary btn-scrolly-rail btn-scrolly-rail--next animate-fade"
                                id="collection-2-btn-next">
                                <span class="visually-hidden">Scroll next items into view</span>
                                <i class="icon fas fa-arrow-right"></i>
                                <path
                                    d="M8.14645 3.14645C8.34171 2.95118 8.65829 2.95118 8.85355 3.14645L12.8536 7.14645C13.0488 7.34171 13.0488 7.65829 12.8536 7.85355L8.85355 11.8536C8.65829 12.0488 8.34171 12.0488 8.14645 11.8536C7.95118 11.6583 7.95118 11.3417 8.14645 11.1464L11.2929 8H2.5C2.22386 8 2 7.77614 2 7.5C2 7.22386 2.22386 7 2.5 7H11.2929L8.14645 3.85355C7.95118 3.65829 7.95118 3.34171 8.14645 3.14645Z"
                                    fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>

                        <div
                            class="card-footer bg-dark rounded-bottom-3 text-muted d-flex justify-content-start align-items-center p-3">
                            <input id="message" tabindex="0" name="message"
                                class="form-control form-control-lg border-0 bg-white px-3 py-2 text-base focus:border-primary focus:outline-none disabled:bg-secondary ltr-placeholder "
                                maxlength="10000" placeholder="Add a message" autocomplete="off" aria-label="question"
                                dir="auto" enterkeyhint="enter"
                                style="height: 42px; border-radius: 0.875rem; min-height: 40px;" />
                            

                                <button type="button" class="btn btn-light ms-2 text-muted" onclick="clearChatHistory()">
                                    <i class="fas fa-trash"></i>
                                </button>

                            <button class="btn btn-primary ms-2" onclick="addMessage()" type="button"
                                aria-label="send message"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </div>

                </section>
            </div>
        </div>
        <div class="row justify-content-between mb-5">
            <div class="col">
                <fieldset class="mb-5">
                    <legend><?php echo t('Basic Settings'); ?></legend>
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label" for="open_ai_key"><?php echo t('Open AI Key'); ?></label>
                                <input class="form-control ccm-input-text" type="text" name="open_ai_key"
                                    id="open_ai_key" value="<?= isset($open_ai_key) ? $open_ai_key : '' ?>" />
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="open_ai_model"><?php echo t('Open AI Model'); ?></label>
                                <input class="form-control ccm-input-text" type="text" name="open_ai_model"
                                    id="open_ai_model" value="<?= isset($open_ai_model) ? $open_ai_model : '' ?>" />
                            </div>
                            <div class="form-group">
                                <label class="form-label"
                                    for="anthropic_ai_key"><?php echo t('Anthropic AI Key'); ?></label>
                                <input class="form-control ccm-input-text" type="text" name="anthropic_ai_key"
                                    id="anthropic_ai_key"
                                    value="<?= isset($anthropic_ai_key) ? $anthropic_ai_key : '' ?>" />
                            </div>
                            <div class="form-group">
                                <label class="form-label"
                                    for="anthropic_ai_model"><?php echo t('Anthropic AI Model'); ?></label>
                                <input class="form-control ccm-input-text" type="text" name="anthropic_ai_model"
                                    id="anthropic_ai_model"
                                    value="<?= isset($anthropic_ai_model) ? $anthropic_ai_model : '' ?>" />
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="ollama_ai_key"><?php echo t('Ollama AI Key'); ?></label>
                                <input class="form-control ccm-input-text" type="text" name="ollama_ai_key"
                                    id="ollama_ai_key" value="<?= isset($ollama_ai_key) ? $ollama_ai_key : '' ?>" />
                            </div>
                            <div class="form-group">
                                <label class="form-label"
                                    for="ollama_ai_model"><?php echo t('Ollama AI Model'); ?></label>
                                <input class="form-control ccm-input-text" type="text" name="ollama_ai_model"
                                    id="ollama_ai_model"
                                    value="<?= isset($ollama_ai_model) ? $ollama_ai_model : '' ?>" />
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>
    </div>
    
    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <div class="float-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save" aria-hidden="true"></i> <?php echo t('Save'); ?>
                </button>
            </div>
        </div>
    </div>
</form>



