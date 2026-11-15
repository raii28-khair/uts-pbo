<?php
require_once 'Tugas.php';

class RutinitasHarian extends Tugas
{
    private string $waktu;
    private string $kategori;

    public function __construct(
        int $id,
        string $waktu,
        string $judul,
        string $kategori = 'Produktif',
        string $deskripsi = '',
        string $prioritas = 'Sedang'
    ) {
        parent::__construct($id, $judul, $deskripsi, date('Y-m-d'), $prioritas);
        $this->setWaktu($waktu);
        $this->setKategori($kategori);
    }

    public function getWaktu(): string    { return $this->waktu; }
    public function getKategori(): string { return $this->kategori; }

    public function setWaktu(string $waktu): void
    {
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $waktu)) {
            throw new InvalidArgumentException("Format jam harus HH:MM (contoh: 06:30).");
        }
        $this->waktu = $waktu;
    }

    public function setKategori(string $kategori): void
    {
        $valid = ['Pagi', 'Kampus', 'Produktif', 'Istirahat', 'Malam'];
        if (!in_array($kategori, $valid, true)) {
            throw new InvalidArgumentException("Kategori rutinitas tidak valid.");
        }
        $this->kategori = $kategori;
    }

    public function getInfo(): string
    {
        $status = $this->isSelesai() ? 'Tercapai' : 'Belum';
        return "[{$this->waktu}] {$this->getJudul()} ({$this->kategori}) - {$status}";
    }
}
