<?php

/**
 * @file
 * Contains \Drupal\Console\Command\User\DeleteCommand.
 */

namespace Drupal\Console\Command\User;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Console\Utils\DrupalApi;

/**
 * Class DeleteCommand
 *
 * @package Drupal\Console\Command\User
 */
class DeleteCommand extends UserBase
{
    /**
     * @var DrupalApi
     */
    protected $drupalApi;

    /**
     * DeleteCommand constructor.
     *
     * @param EntityTypeManagerInterface $entityTypeManager
     * @param DrupalApi                  $drupalApi
     */
    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        DrupalApi $drupalApi
    ) {
        $this->drupalApi = $drupalApi;
        parent::__construct($entityTypeManager);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('user:delete')
            ->setDescription($this->trans('commands.user.delete.description'))
            ->addOption(
                'user',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.user.delete.options.user')
            )
            ->addOption(
                'roles',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                $this->trans('commands.user.delete.options.roles')
            )->setAliases(['ud']);
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $user = $this->getUserOption();

        $roles = $input->getOption('roles');

        if (!$user && !$roles) {
            $systemRoles = $this->drupalApi->getRoles(false, false, false);
            $roles = $this->getIo()->choice(
                $this->trans('commands.user.delete.questions.roles'),
                array_values($systemRoles),
                null,
                true
            );

            $roles = array_map(
                function ($role) use ($systemRoles) {
                    return array_search($role, $systemRoles);
                },
                $roles
            );

            $input->setOption('roles', $roles);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $user = $input->getOption('user');

        if ($user) {
            $userEntity = $this->getUserEntity($user);
            if (!$userEntity) {
                $this->getIo()->error(
                    sprintf(
                        $this->trans('commands.user.delete.errors.invalid-user'),
                        $user
                    )
                );

                return 1;
            }

            if ($userEntity->id() <= 1) {
                $this->getIo()->error(
                    sprintf(
                        $this->trans('commands.user.delete.errors.invalid-user'),
                        $user
                    )
                );

                return 1;
            }

            try {
                $userEntity->delete();
                $this->getIo()->info(
                    sprintf(
                        $this->trans('commands.user.delete.messages.user-deleted'),
                        $userEntity->getAccountName()
                    )
                );

                return 0;
            } catch (\Exception $e) {
                $this->getIo()->error($e->getMessage());

                return 1;
            }
        }

        $roles = $input->getOption('roles');

        if ($roles) {
            $roles = is_array($roles)?$roles:[$roles];

            $query = $this->entityTypeManager->getStorage('user')->getQuery();
            $query->condition('roles', array_values($roles), 'IN')
                ->condition('uid', 1, '>');
            $results = $query->execute();

            $users = $this->entityTypeManager
                ->getStorage('user')
                ->loadMultiple($results);

            $tableHeader = [
              $this->trans('commands.user.debug.messages.user-id'),
              $this->trans('commands.user.debug.messages.username'),
            ];

            $tableRows = [];
            foreach ($users as $user => $userEntity) {
                try {
                    $userEntity->delete();
                    $tableRows['success'][] = [$user, $userEntity->getAccountName()];
                } catch (\Exception $e) {
                    $tableRows['error'][] = [$user, $userEntity->getAccountName()];
                    $this->getIo()->error($e->getMessage());

                    return 1;
                }
            }

            if ($tableRows['success']) {
                $this->getIo()->table($tableHeader, $tableRows['success']);
                $this->getIo()->success(
                    sprintf(
                        $this->trans('commands.user.delete.messages.users-deleted'),
                        count($tableRows['success'])
                    )
                );

                return 0;
            }
        }
    }
}
