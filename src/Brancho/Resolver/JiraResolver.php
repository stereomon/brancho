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
        'story' => 'master',
        'task' => 'master',
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

        $parentJiraIssue = $this->getParentJiraIssue($jiraIssue, $config);

        $type = $this->getType($jiraIssue, $parentJiraIssue);
        $prefix = $this->getPrefix($jiraIssue);

        return sprintf(
            '%s/%s%s/%s-%s',
            $type,
            $this->getParentIssue($parentJiraIssue, $filter),
            $filter->filter($issue),
            $prefix,
            $filter->filter($jiraIssue['fields']['summary'])
        );
    }

    /**
     * @param array $jiraIssue
     * @param array $parentJiraIssue
     *
     * @return string
     */
    protected function getType(array $jiraIssue, array $parentJiraIssue): string
    {
        if (isset($parentJiraIssue['fields']['issuetype']['name'])) {
            return $this->mapIssueType($parentJiraIssue['fields']['issuetype']['name']);
        }

        return $this->mapIssueType($jiraIssue['fields']['issuetype']['name']);
    }

    /**
     * @param array $jiraIssue
     *
     * @return string
     */
    protected function getPrefix(array $jiraIssue): string
    {
        return $this->mapIssueTypeToPrefix($jiraIssue['fields']['issuetype']['name']);
    }

    /**
     * @param array $jiraIssue
     * @param array $config
     *
     * @return array
     */
    protected function getParentJiraIssue(array $jiraIssue, array $config): array
    {
        $parentJiraIssue = [];

        if (isset($jiraIssue['fields']['customfield_10008'])) {
            $parentJiraIssue = $this->getFactory()
                ->createJira()
                ->getJiraIssue($jiraIssue['fields']['customfield_10008'], $config);
        }

        return $parentJiraIssue;
    }

    /**
     * @param array $parentJiraIssue
     * @param FilterInterface $filter
     *
     * @return string
     */
    protected function getParentIssue(array $parentJiraIssue, FilterInterface $filter): string
    {
        $parentIssue = '';

        if (isset($parentJiraIssue['key'])) {
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
