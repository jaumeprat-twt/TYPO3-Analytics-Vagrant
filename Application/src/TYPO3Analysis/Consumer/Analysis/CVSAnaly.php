<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Analysis;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class CVSAnaly extends ConsumerAbstract {

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription() {
        return 'Executes the CVSAnaly analysis on a given folder and stores the results in database.';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize() {
        $this->setQueue('analysis.cvsanaly');
        $this->setRouting('analysis.cvsanaly');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass     $message
     * @return void
     */
    public function process($message) {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        // If there is no directory to analyse, exit here
        if (is_dir($messageData->checkoutDir) !== true) {
            $this->getLogger()->critical('Directory does not exist', array('directory' => $messageData->checkoutDir));
            $this->acknowledgeMessage($message);
            return;
        }

        $this->getLogger()->info('Analyze directory with CVSAnaly', array('directory' => $messageData->checkoutDir));

        try {
            $extensions = $this->getCVSAnalyExtensions($this->getConfig());
        } catch (\Exception $e) {
            $this->acknowledgeMessage($this->getMessage());
            return;
        }

        $command = $this->buildCVSAnalyCommand($this->getConfig(), $messageData->project, $messageData->checkoutDir, $extensions);
        try {
            $this->executeCommand($command);
        } catch (\Exception $e) {
            $this->acknowledgeMessage($this->getMessage());
            return;
        }

        $this->acknowledgeMessage($message);
    }

    /**
     * Builds the CVSAnaly command
     *
     * @param array     $config
     * @param string    $project
     * @param string    $directory
     * @param string    $extensions
     * @return string
     */
    private function buildCVSAnalyCommand($config, $project, $directory, $extensions) {
        $projectConfig = $config['Projects'][$project];

        $command = escapeshellcmd($config['Application']['CVSAnaly']['Binary']);
        $command .= ' --db-driver ' . escapeshellarg('mysql');
        $command .= ' --db-hostname ' . escapeshellarg($config['MySQL']['Host']);
        $command .= ' --db-user ' . escapeshellarg($config['MySQL']['Username']);
        $command .= ' --db-password ' . escapeshellarg($config['MySQL']['Password']);
        $command .= ' --db-database ' . escapeshellarg($projectConfig['MySQL']['Database']);
        //$command .= ' --extensions ' . escapeshellarg($extensions);
        $command .= ' --metrics-all';
        $command .= ' ' . escapeshellarg($directory);

        return $command;
    }

    /**
     * Returns all active and usable extensions of CVSAnaly
     *
     * @param array $config
     * @return string
     */
    private function getCVSAnalyExtensions($config) {
        $command = escapeshellcmd($config['Application']['CVSAnaly']['Binary']);
        $command .= ' --list-extensions';

        $extensions = $this->executeCommand($command);
        $extensions = implode('', $extensions);

        if ($extensions) {
            $extensions = str_replace(' ', '', $extensions);
        }

        return $extensions;
    }
}