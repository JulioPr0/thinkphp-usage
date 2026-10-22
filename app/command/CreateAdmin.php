<?php
declare(strict_types=1);

namespace app\command;

use app\exception\ValidationException;
use app\service\AuthService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;
use think\facade\Db;

final class CreateAdmin extends Command
{
    protected function configure(): void
    {
        $this->setName('admin:create')->setDescription('Buat admin atau reset password admin melalui CLI.')
            ->addArgument('email', Argument::REQUIRED, 'Email admin.')
            ->addArgument('name', Argument::OPTIONAL, 'Nama admin.', 'Administrator')
            ->addOption('reset', null, Option::VALUE_NONE, 'Reset password akun yang sudah ada dan akhiri semua sesinya.');
    }

    protected function execute(Input $input, Output $output): int
    {
        $email = strtolower(trim((string) $input->getArgument('email')));
        $name = trim((string) $input->getArgument('name'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || $name === '' || mb_strlen($name) > 100) {
            $output->writeln('<error>Email atau nama tidak valid.</error>');
            return 1;
        }
        $existing = Db::name('users')->where('email', $email)->find();
        if (($existing && !$input->getOption('reset')) || (!$existing && $input->getOption('reset'))) {
            $output->writeln('<error>Akun sudah ada atau target reset tidak ditemukan. Periksa email dan opsi --reset.</error>');
            return 1;
        }
        $password = getenv('ADMIN_PASSWORD');
        $fromEnvironment = is_string($password) && $password !== '';
        if (!$fromEnvironment) {
            $password = (string) $output->askHidden($input, 'Password (12–72 byte): ');
        }
        $confirmation = $fromEnvironment ? $password : (string) $output->askHidden($input, 'Ulangi password: ');
        try {
            AuthService::validatePassword($password, $confirmation);
        } catch (ValidationException $exception) {
            $output->writeln('<error>' . implode(' ', $exception->errors()) . '</error>');
            return 1;
        }
        $data = ['password' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => date('Y-m-d H:i:s')];
        if ($existing) {
            $data['session_version'] = Db::raw('session_version + 1');
            Db::name('users')->where('id', $existing['id'])->update($data);
        } else {
            $data += ['name' => $name, 'email' => $email, 'created_at' => $data['updated_at']];
            Db::name('users')->insert($data);
        }
        $output->writeln('<info>Akun admin siap digunakan. Password tidak ditampilkan atau disimpan sebagai teks biasa.</info>');
        return 0;
    }
}
