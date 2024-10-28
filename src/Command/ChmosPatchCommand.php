<?php

namespace App\Command;

use App\Entity\Project;
use App\Entity\User;
use App\Service\ChmosService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:chmos:patch')]
class ChmosPatchCommand extends Command
{
    protected ManagerRegistry $doctrine;
    protected ChmosService $chmosService;
    protected MailerInterface $mailer;
    protected string $mailerFrom;
    protected string $host;

    public function __construct(ManagerRegistry $doctrine, ChmosService $chmosService, MailerInterface $mailer, string $mailerFrom, string $host)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
        $this->chmosService = $chmosService;
        $this->mailer = $mailer;
        $this->mailerFrom = $mailerFrom;
        $this->host = $host;
    }

    protected function configure()
    {
        $this
            ->setDescription('Patch projects with chmos data.')
            ->addOption('ids', null, InputOption::VALUE_OPTIONAL, 'Ids of projects to patch.')
            ->addOption('properties', null, InputOption::VALUE_REQUIRED, 'Comma separated list of properties to patch.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Whether properties should be overwritten.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var Project[] $projects */
        $qb = $this->doctrine->getManager()->createQueryBuilder();
        $qb
            ->select('p')
            ->from(Project::class, 'p')
        ;

        if($input->getOption('ids'))  {
            $ids = array_map('trim', explode(',', $input->getOption('ids')));
            $qb
                ->where('p.id IN (:ids)')
                ->setParameter('ids', $ids)
            ;
        }

        $projects = $qb->getQuery()->getResult();

        $projects = array_values(array_filter($projects, function ($project) {
            return $project->getProjectCode();
        }));

        if(!$input->getOption('properties')) {
            $io->error('You must provide at least one property.');
            return 1;
        }

        $properties = array_map('trim', explode(',', $input->getOption('properties')));

        $count = 0;
        $errorCount = 0;

        foreach($projects as $project) {
            try {
                if($this->chmosService->performProjectPatch($project, $properties, $input->getOption('force'))) {
                    $count++;
                    $io->info(sprintf('Patch of project %s succeeded..', $project->getProjectCode()));
                }
            } catch (\Throwable $exception) {
                $errorCount++;
                $io->error(sprintf('Patch of project %s failed..', $project->getProjectCode()));

            }
        }

        if($errorCount > 0) {
            $io->warning(sprintf('CHMOS patch partially failed. %s of %s projects were patched, while %s failed to patch successfully.', $count, count($projects), $errorCount));

            return 1;
        }

        $io->success(sprintf('CHMOS patch completed. %s of %s projects were patched.', $count, count($projects)));

        return 0;
    }
}
