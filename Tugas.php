<?php
class Tugas
{
    private int $id;
    private string $judul;
    private string $deskripsi;
    private string $tenggat;
    private string $prioritas;
    private bool $selesai;

    public function __construct(
        int $id,
        string $judul,
        string $deskripsi,
        string $tenggat,
        string $prioritas
    ) {
        $this->id        = $id;
        $this->setJudul($judul);
        $this->setDeskripsi($deskripsi);
        $this->setTenggat($tenggat);
        $this->setPrioritas($prioritas);
        $this->selesai   = false;
    }

    public function getId(): int           { return $this->id; }
    public function getJudul(): string     { return $this->judul; }
    public function getDeskripsi(): string { return $this->deskripsi; }
    public function getTenggat(): string   { return $this->tenggat; }
    public function getPrioritas(): string { return $this->prioritas; }
    public function isSelesai(): bool      { return $this->selesai; }

    public function setJudul(string $judul): void
    {
        $judul = trim($judul);
        if ($judul === '') {
            throw new InvalidArgumentException("Judul tidak boleh kosong.");
        }
        $this->judul = $judul;
    }

    public function setDeskripsi(string $deskripsi): void
    {
        $this->deskripsi = trim($deskripsi);
    }

    public function setTenggat(string $tenggat): void
    {
        $d = DateTime::createFromFormat('Y-m-d', $tenggat);
        if (!$d || $d->format('Y-m-d') !== $tenggat) {
            throw new InvalidArgumentException("Format tanggal harus Y-m-d.");
        }
        $this->tenggat = $tenggat;
    }

    public function setPrioritas(string $prioritas): void
    {
        $valid = ['Rendah', 'Sedang', 'Tinggi'];
        if (!in_array($prioritas, $valid, true)) {
            throw new InvalidArgumentException("Prioritas harus Rendah, Sedang, atau Tinggi.");
        }
        $this->prioritas = $prioritas;
    }

    public function tandaiSelesai(): void { $this->selesai = true; }
    public function toggleSelesai(): void { $this->selesai = !$this->selesai; }

    public function sisaHari(): int
    {
        $hariIni = new DateTime('today');
        $batas   = new DateTime($this->tenggat);
        return (int) $hariIni->diff($batas)->format('%r%a');
    }

    public function isTerlambat(): bool
    {
        return !$this->selesai && $this->sisaHari() < 0;
    }

    public function getInfo(): string
    {
        $status = $this->selesai ? 'Selesai' : ($this->isTerlambat() ? 'Terlambat' : 'Belum selesai');
        return "{$this->judul} | Prioritas: {$this->prioritas} | Tenggat: {$this->tenggat} | Status: {$status}";
    }
}
