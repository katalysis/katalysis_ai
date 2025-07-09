<?php

namespace KatalysisAi\Command\Task\Controller;

use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Runner\CommandTaskRunner;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Core\Command\Task\Controller\AbstractController;

use KatalysisAi\Command\BuildRagIndexCommand;


class BuildRagIndexController extends AbstractController
{
    public function getName(): string
    {
        return t("Build RAG Index");
    }

    public function getDescription(): string
    {
        return t("Builds the RAG index.");
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {

      $command = new BuildRagIndexCommand();
      return new CommandTaskRunner($task, $command, t('Success, RAG index built.'));

    }

}
