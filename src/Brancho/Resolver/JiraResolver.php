<?php

namespace Brancho\Resolver;

use Brancho\Context\ContextInterface;
use Laminas\Filter\FilterInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class JiraResolver extends AbstractResolver
{
    /**
     * @var string[]
     */
    protected $issueTypeMap = [
        'epic' => 'feature',
        'task' => 'feature',
        'bug' => 'bugfix',
    ];

    /**
     * @var string[]
     */
    protected $issueTypeToPrefixMap = [
        'epic' => 'master',
        'bug' => 'master',
        'story' => 'dev',
        'task' => 'dev',
    ];

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param ContextInterface $context
     *
     * @return string|null
     */
    public function resolve(InputInterface $input, OutputInterface $output, ContextInterface $context): ?string
    {
        $question = new Question('Please enter the Jira Ticket number e.g. "rk-123": ');
        $helper = new QuestionHelper();

        $issue = $helper->ask($input, $output, $question);
        $config = $context->getConfig()['jira'];
        $filter = $context->getFilter();

        $jiraIssue = $this->getFactory()->createJira()->getJiraIssue($issue, $config);

        if (isset($jiraIssue['errorMessages'])) {
            foreach ($jiraIssue['errorMessages'] as $errorMessage) {
                $output->writeln(sprintf('<fg=red>%s</>', $errorMessage));
            }

            return null;
        }

        $summary = $jiraIssue['fields']['summary'];
        $issueType = $jiraIssue['fields']['issuetype']['name'];

        $mappedType = $this->mapIssueType($issueType);
        $prefix = $this->mapIssueTypeToPrefix($issueType);

        return sprintf(
            '%s/%s%s/%s-%s',
            $mappedType,
            $this->getParentIssue($jiraIssue, $config, $filter),
            $filter->filter($issue),
            $prefix,
            $filter->filter($summary)
        );
    }

    /**
     * @param array $jiraIssue
     * @param array $config
     * @param FilterInterface $filter
     *
     * @return string
     */
    protected function getParentIssue(array $jiraIssue, array $config, FilterInterface $filter): string
    {
        $parentIssue = '';

        if (isset($jiraIssue['fields']['customfield_10008'])) {
            $parentJiraIssue = $this->getFactory()
                ->createJira()
                ->getJiraIssue($jiraIssue['fields']['customfield_10008'], $config);

            $parentIssue = sprintf('%s/', $filter->filter($parentJiraIssue['key']));
        }

        return $parentIssue;
    }

    /**
     * @param string $issueType
     *
     * @return string
     */
    protected function mapIssueType(string $issueType): string
    {
        return $this->issueTypeMap[strtolower($issueType)];
    }

    /**
     * @param string $issueType
     *
     * @return string
     */
    protected function mapIssueTypeToPrefix(string $issueType): string
    {
        return $this->issueTypeToPrefixMap[strtolower($issueType)];
    }
}
