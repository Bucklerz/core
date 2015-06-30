<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Models\Mship\Account;
use Enums\Account\State as EnumState;

class SyncCommunity extends aCommand
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'Sync:Community';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync membership data from Core to Community.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        if ($this->option('verbose')) {
            $verbose = true;
        } else {
            $verbose = false;
        }

        require_once('/var/www/community_beta/init.php');
        require_once(IPS\ROOT_PATH . '/system/Member/Member.php');
        require_once(IPS\ROOT_PATH . '/system/Db/Db.php');

        $members = \IPS\Db::i()->select('m.member_id, m.vatsim_cid, m.name, m.email, m.member_title, p.field_12, p.field_13, p.field_14', ['core_members', 'm'])
                               ->join(['core_pfields_content', 'p'], 'm.member_id = p.member_id');

        $countTotal = $members->count();
        $countSuccess = 0;
        $countFailure = 0;

        for ($i = 0; $i < $countTotal; $i++) {
            $members->next();

            $member = $members->current();

            if (empty($member['vatsim_cid']) || !is_numeric($member['vatsim_cid'])) {
                if ($verbose) {
                    $this->output->writeln('<error>FAILURE: ' . $member['member_id'] . ' has no valid CID.</error>');
                }
                $countFailure++;
                continue;
            }

            if ($verbose) {
                $this->output->write('<info>' . str_pad($member['member_id'], 4) . ' // ' . str_pad($member['vatsim_cid'], 7) . '</info>');
            }

            $member_core = Account::where('account_id', $member['vatsim_cid'])->with('states', 'qualifications')->first();
            if ($member_core === NULL) {
                if ($verbose) {
                    $this->output->writeln(' // <error>FAILURE: cannot retrieve member ' . $member['member_id'] . ' from Core.</error>');
                }
                $countFailure++;
                continue;
            }

            // Sort out their email
            $emailLocal = false;
            $email = $member_core->primary_email;
            if (empty($email)) {
                $email = $member['email'];
                $emailLocal = true;
            }

            // State
            //$state = $member_core->getIsStateAttribute(EnumState::DIVISION)->first()->state ? 'Division Member' : 'International Member';
            //$state = $member_core->getIsStateAttribute(EnumState::VISITOR)->first()->state ? 'Visiting Member' : $state;
            $state = $member_core->states()->where('state', '=', EnumState::DIVISION)->first()->state ? 'Division Member' : 'International Member';
            $state = $member_core->states()->where('state', '=', EnumState::VISITOR)->first()->state ? 'Visiting Member' : $state;

            // ATC rating
            $aRatingString = $member_core->qualification_atc->qualification->name_long;

            // Sort out the pilot rating.
            $pRatingString = $member_core->qualifications_pilot_string;

            // Check for changes
            $changeEmail = strcasecmp($member['email'], $email);
            $changeName = strcmp($member['name'], $member_core->name_first . ' ' . $member_core->name_last);
            $changeState = strcasecmp($member['member_title'], $state);
            $changeCID = strcmp($member['field_12'], $member_core->account_id);
            $changeARating = strcmp($member['field_13'], $aRatingString);
            $changePRating = strcmp($member['field_14'], $pRatingString);
            $changesPending = $changeEmail || $changeName || $changeState || $changeCID
                              || $changeARating || $changePRating || $changeERating;

            // Confirm the data
            if ($verbose) {
                $this->output->write(' // ID: ' . $member_core->account_id);
                $this->output->write(' // Email (' . ($emailLocal ? 'local' : "latest") . "):" . $email . ($changeEmail ? "(changed)" : ""));
                $this->output->write(' // Display: ' . $member_core->name_first . " " . $member_core->name_last . ($changeName ? "(changed)" : ""));
                $this->output->write(' // State: ' . $state . ($changeState ? "(changed)" : ""));
                $this->output->write(' // ATC rating: ' . $aRatingString);
                $this->output->write(' // Pilot ratings: ' . $pRatingString);
                $this->output->write(' // Extra ratings: ' . $eRatingString);
            }

            if ($changesPending) {
                try {
                    // ActiveRecord / Member fields
                    $ips_member = \IPS\Member::load($member['member_id']);
                    $ips_member->name = $member_core->name_first . ' ' . $member_core->name_last;
                    $ips_member->email = $email;
                    $ips_member->member_title = $state;
                    $ips_member->save();

                    // Profile fields (raw update)
                    $update = [
                        'field_12' => $member_core->account_id, // VATSIM CID
                        'field_13' => $aRatingString, // Controller Rating
                        'field_14' => $pRatingString, // Pilot Ratings
                    ];
                    $updated_rows = \IPS\Db::i()->update('core_pfields_content', $update, ['member_id=?', $member['member_id']]);

                    if ($updated_rows !== 1) {
                        throw new Exception($updated_rows . ' profile field records updated for member ' . $member['member_id'] . '.');
                    }

                    if ($verbose) {
                        $this->output->writeln(' // <info>Details saved successfully.</info>');
                    }
                    $countSuccess++;
                } catch (Exception $e) {
                    $countFailure++;
                    $this->output->writeln(' // <error>Error saving ' . $member_core->account_id . ' details to forum.</error>' . $e->getMessage());
                }
            } elseif ($verbose) {
                $this->output->writeln(' // <info>No changes required.</info>');
            }
        }

        if ($verbose) {
            $this->output->writeln('Run Results:');
            $this->output->writeln('Total Accounts: '.$countTotal);
            $this->output->writeln('Successful Updates: '.$countSuccess);
            $this->output->writeln('Failed Updates: '.$countFailure);
        }
    }

    /**
     * Get the console command optionmship_account_state.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('force-update', 'f', InputOption::VALUE_OPTIONAL, 'If specified, only this CID will be checked.', 0),
        );
    }
}
