<?php
declare(strict_types=1);

namespace app\command;

use app\service\ArticleService;
use app\service\Migrator;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use think\facade\Db;

final class Install extends Command
{
    protected function configure(): void
    {
        $this->setName('app:install')->setDescription('Jalankan migrasi database secara aman dan idempoten.')
            ->addOption('seed', null, Option::VALUE_NONE, 'Tambahkan contoh hanya jika tabel artikel masih kosong.');
    }

    protected function execute(Input $input, Output $output): int
    {
        $migrations = (new Migrator())->run(Db::connect()->connect(), root_path() . 'database/migrations');
        foreach ($migrations as $migration) {
            $output->writeln('<info>Migrasi: ' . $migration . '</info>');
        }
        if (!$migrations) {
            $output->writeln('<info>Database sudah menggunakan skema terbaru.</info>');
        }
        if ($input->getOption('seed') && Db::name('articles')->count() === 0) {
            Db::transaction(static function (): void {
                $now = date('Y-m-d H:i:s');
                foreach (['Panduan IT' => 'panduan-it', 'Pengembangan' => 'pengembangan', 'Catatan' => 'catatan'] as $name => $slug) {
                    if (!Db::name('categories')->where('slug', $slug)->find()) {
                        Db::name('categories')->insert(['name' => $name, 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now]);
                    }
                }
                $category = (int) Db::name('categories')->where('slug', 'panduan-it')->value('id');
                $service = new ArticleService();
                foreach ([
                    ['Membangun kebiasaan dokumentasi yang baik', 'Catatan kecil hari ini dapat menghemat banyak waktu esok hari.', 'Dokumentasi membantu tim memahami keputusan, mengulangi proses yang benar, dan menyelesaikan masalah dengan lebih cepat. Mulailah dengan mencatat tujuan, langkah, hasil, serta hal yang perlu diperbaiki.\n\nGunakan bahasa yang singkat dan berikan contoh konkret. Perbarui catatan saat proses berubah.', 'published'],
                    ['Langkah awal troubleshooting jaringan', 'Pendekatan terstruktur untuk menemukan sumber masalah.', 'Mulai dari lingkup masalah: apakah satu perangkat atau seluruh jaringan yang terdampak? Periksa kabel, alamat IP, gateway, dan DNS.\n\nUji setiap asumsi satu per satu dan dokumentasikan hasil sebelum mengubah konfigurasi.', 'published'],
                    ['Ide untuk artikel berikutnya', 'Ruang untuk mengembangkan gagasan sebelum diterbitkan.', 'Tuliskan tujuan, calon pembaca, dan kerangka artikel. Simpan sebagai draft sambil mengumpulkan contoh yang relevan.', 'draft'],
                ] as [$title, $summary, $content, $status]) {
                    $service->save(['title' => $title, 'summary' => $summary, 'content' => str_replace('\\n', "\n", $content), 'status' => $status, 'category_id' => $category]);
                }
            });
            $output->writeln('<info>Contoh kategori dan artikel berhasil ditambahkan.</info>');
        }
        $output->writeln('Buat admin: php think admin:create admin@example.com "Administrator"');
        return 0;
    }
}
