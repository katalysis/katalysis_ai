<?php 
namespace Concrete\Package\KatalysisAi;

use Config;
use Page;
use Concrete\Core\Package\Package;
use SinglePage;
use View;
use AssetList;
use Asset;
use Concrete\Core\Command\Task\Manager as TaskManager;
use KatalysisAi\Command\Task\Controller\BuildRagIndexController;

class Controller extends Package
{
    protected $pkgHandle = 'katalysis_ai';
    protected $appVersionRequired = '9.3';
    protected $pkgVersion = '0.1.1';
    protected $pkgAutoloaderRegistries = [
        'src' => 'KatalysisAi'
    ];

    protected $single_pages = array(
        '/dashboard/system/basics/ai' => array(
            'cName' => 'AI'
        )
    );

    public function getPackageName()
    {
        return t("Katalysis AI");
    }

    public function getPackageDescription()
    {
        return t("Adds AI capabilities");
    }

    public function on_start()
    {
        $this->setupAutoloader();

        $version = $this->getPackageVersion();

        $al = AssetList::getInstance();
        $al->register('css', 'katalysis-ai', 'css/katalysis-ai.css', ['version' => $version, 'position' => Asset::ASSET_POSITION_HEADER, 'minify' => false, 'combine' => false], $this);
        
        $manager = $this->app->make(TaskManager::class);
		$manager->extend('build_rag_index', function () {
			return new BuildRagIndexController();
		});
    }

    private function setupAutoloader()
    {
        if (file_exists($this->getPackagePath() . '/vendor')) {
            require_once $this->getPackagePath() . '/vendor/autoload.php';
        }
    }

    public function install()
    {
        $this->setupAutoloader();

        $pkg = parent::install();

        Config::save('katalysis.ai.open_ai_key', '');
        Config::save('katalysis.ai.open_ai_model', 'gpt-4o-mini');
        Config::save('katalysis.ai.anthropic_key', '');
        Config::save('katalysis.ai.anthropic_model', 'claude-2');
        Config::save('katalysis.ai.ollama_key', '');
        Config::save('katalysis.ai.ollama_url', '');

        $this->installPages($pkg);
        $this->installContentFile('build_rag_index.xml');
        
    }

    public function upgrade()
    {
        $this->installContentFile('build_rag_index.xml');


    }


    /**
     * @param Package $pkg
     * @return void
     */
    protected function installPages($pkg)
    {
        foreach ($this->single_pages as $path => $value) {
            if (!is_array($value)) {
                $path = $value;
                $value = array();
            }
            $page = Page::getByPath($path);
            if (!$page || $page->isError()) {
                $single_page = SinglePage::add($path, $pkg);

                if ($value) {
                    $single_page->update($value);
                }
            }
        }
    }
}
