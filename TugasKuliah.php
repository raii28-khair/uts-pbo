<?php
require_once 'Tugas.php';

class TugasKuliah extends Tugas
{
    private string $mataKuliah;
    private string $dosen;
    private string $jenisTugas;
    private int $bobotNilai;
    private string $linkPengumpulan;

    public function __construct(
        int $id,
        string $judul,
        string $deskripsi,
        string $tenggat,
        string $prioritas,
        string $mataKuliah,
        string $dosen,
        string $jenisTugas,
        int $bobotNilai,
        string $linkPengumpulan = ''
    ) {
        parent::__construct($id, $judul, $deskripsi, $tenggat, $prioritas);
        $this->setMataKuliah($mataKuliah);
        $this->setDosen($dosen);
        $this->setJenisTugas($jenisTugas);
        $this->setBobotNilai($bobotNilai);
        $this->setLinkPengumpulan($linkPengumpulan);
    }

    public function getMataKuliah(): string      { return $this->mataKuliah; }
    public function getDosen(): string           { return $this->dosen; }
    public function getJenisTugas(): string      { return $this->jenisTugas; }
    public function getBobotNilai(): int         { return $this->bobotNilai; }
    public function getLinkPengumpulan(): string { return $this->linkPengumpulan; }

    public function setMataKuliah(string $mk): void
    {
        $mk = trim($mk);
        if ($mk === '') {
            throw new InvalidArgumentException("Mata kuliah tidak boleh kosong.");
        }
        $this->mataKuliah = $mk;
    }

    public function setDosen(string $dosen): void { $this->dosen = trim($dosen); }

    public function setJenisTugas(string $jenis): void
    {
        if (!in_array($jenis, ['Individu', 'Kelompok'], true)) {
            throw new InvalidArgumentException("Jenis tugas harus Individu atau Kelompok.");
        }
        $this->jenisTugas = $jenis;
    }

    public function setBobotNilai(int $bobot): void
    {
        if ($bobot < 0 || $bobot > 100) {
            throw new InvalidArgumentException("Bobot nilai harus 0 sampai 100.");
        }
        $this->bobotNilai = $bobot;
    }

    public function setLinkPengumpulan(string $link): void { $this->linkPengumpulan = trim($link); }

    public function isPenting(): bool
    {
        return $this->bobotNilai >= 20 || $this->getPrioritas() === 'Tinggi';
    }

    public function getPengingat(): string
    {
        if ($this->isSelesai()) return "Sudah selesai. Kerja bagus!";
        $sisa = $this->sisaHari();
        if ($sisa < 0)  return "Terlambat " . abs($sisa) . " hari!";
        if ($sisa === 0) return "Deadline hari ini!";
        return "Sisa {$sisa} hari lagi.";
    }

    public function getInfo(): string
    {
        return parent::getInfo()
            . " | MK: {$this->mataKuliah} | Dosen: {$this->dosen}"
            . " | {$this->jenisTugas} | Bobot: {$this->bobotNilai}%";
    }
}
