<?php

namespace Src\DemoData\Application;

/**
 * The word and statement banks the demo psychological batteries are built from. Every item is original, written for the demo in the style of the subtests
 * that intelligence batteries such as the IST use (sentence completion, word odd-one-out, analogies, similarities, arithmetic, number series), not copied from
 * any published test. The texts are Indonesian.
 */
final class DemoPsychometricBanks
{
    /**
     * Analogy pairs by relation: the second word follows from the first in the same way inside one relation.
     *
     * @return list<list<array{string, string}>>
     */
    public static function analogies(): array
    {
        return [
            [['pisau', 'memotong'], ['pena', 'menulis'], ['sapu', 'menyapu'], ['kunci', 'membuka'], ['kompor', 'memasak'], ['kacamata', 'melihat'], ['payung', 'berteduh'], ['palu', 'memukul']],
            [['roda', 'mobil'], ['daun', 'pohon'], ['halaman', 'buku'], ['jari', 'tangan'], ['atap', 'rumah'], ['layar', 'ponsel'], ['sayap', 'burung'], ['kancing', 'baju']],
            [['apel', 'buah'], ['bayam', 'sayuran'], ['kucing', 'hewan'], ['mawar', 'bunga'], ['besi', 'logam'], ['Jakarta', 'kota'], ['Senin', 'hari'], ['merah', 'warna']],
            [['tinggi', 'rendah'], ['panas', 'dingin'], ['terang', 'gelap'], ['cepat', 'lambat'], ['naik', 'turun'], ['kaya', 'miskin'], ['jauh', 'dekat'], ['awal', 'akhir']],
            [['dokter', 'rumah sakit'], ['guru', 'sekolah'], ['petani', 'sawah'], ['nelayan', 'laut'], ['hakim', 'pengadilan'], ['koki', 'dapur'], ['pilot', 'pesawat'], ['atlet', 'stadion']],
        ];
    }

    /**
     * Categories with their members, for the similarities and odd-one-out items.
     *
     * @return array<string, list<string>>
     */
    public static function categories(): array
    {
        return [
            'buah' => ['apel', 'jeruk', 'mangga', 'pisang', 'anggur', 'melon'],
            'kendaraan' => ['mobil', 'bus', 'kereta', 'sepeda', 'kapal', 'pesawat'],
            'hewan' => ['kucing', 'sapi', 'ayam', 'ikan', 'kuda', 'bebek'],
            'perabot' => ['kursi', 'meja', 'lemari', 'ranjang', 'rak', 'sofa'],
            'pakaian' => ['kemeja', 'celana', 'jaket', 'rok', 'kaus', 'sarung'],
            'alat tulis' => ['pena', 'pensil', 'penghapus', 'penggaris', 'spidol', 'kertas'],
            'warna' => ['merah', 'biru', 'hijau', 'kuning', 'ungu', 'jingga'],
            'profesi' => ['dokter', 'guru', 'hakim', 'perawat', 'insinyur', 'arsitek'],
            'alat musik' => ['gitar', 'piano', 'biola', 'seruling', 'drum', 'angklung'],
            'olahraga' => ['sepak bola', 'renang', 'tenis', 'bulu tangkis', 'voli', 'basket'],
            'planet' => ['Bumi', 'Mars', 'Venus', 'Jupiter', 'Saturnus', 'Merkurius'],
            'kota' => ['Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Makassar', 'Semarang'],
            'bumbu dapur' => ['garam', 'merica', 'kunyit', 'jahe', 'ketumbar', 'bawang'],
            'logam' => ['besi', 'tembaga', 'emas', 'perak', 'seng', 'timah'],
        ];
    }

    /**
     * Sentence completion: the sentence with a gap, the right word and three wrong ones.
     *
     * @return list<array{string, string, string, string, string}>
     */
    public static function sentences(): array
    {
        return [
            ['Seorang petani menanam padi di ___.', 'sawah', 'pasar', 'kantor', 'laut'],
            ['Hujan deras membuat jalan menjadi ___.', 'licin', 'kering', 'debu', 'sepi'],
            ['Karena lupa membawa kunci, ia tidak bisa ___ pintu rumahnya.', 'membuka', 'menutup', 'mengecat', 'menjual'],
            ['Pegawai yang rajin biasanya ___ pekerjaannya tepat waktu.', 'menyelesaikan', 'mengabaikan', 'menghindari', 'melupakan'],
            ['Untuk menjaga kesehatan, kita perlu makan teratur dan ___ cukup.', 'istirahat', 'bekerja', 'bepergian', 'berbelanja'],
            ['Rapat ditunda ___ banyak peserta yang berhalangan hadir.', 'karena', 'supaya', 'sehingga', 'walaupun'],
            ['Semakin tinggi gunung, udara di puncaknya semakin ___.', 'dingin', 'panas', 'lembap', 'padat'],
            ['Pak Anwar adalah seorang ___ yang merawat orang sakit di rumah sakit.', 'perawat', 'pelaut', 'pengacara', 'pedagang'],
            ['Agar tidak terlambat, ia berangkat lebih ___ dari biasanya.', 'awal', 'lambat', 'jauh', 'sedikit'],
            ['Hasil penjualan bulan ini ___ dibandingkan bulan lalu, sehingga target tercapai.', 'meningkat', 'menyusut', 'hilang', 'berhenti'],
            ['Seorang pemimpin yang baik mau ___ pendapat anggota timnya.', 'mendengarkan', 'menyembunyikan', 'meniru', 'menghindari'],
            ['Laporan itu harus diperiksa ___ sebelum dikirim agar tidak ada kesalahan.', 'teliti', 'sekilas', 'sembarangan', 'terburu-buru'],
        ];
    }

    /**
     * Triples for the syllogism items: every X is a Y and every Y is a Z.
     *
     * @return list<array{string, string, string}>
     */
    public static function syllogisms(): array
    {
        return [
            ['kucing', 'mamalia', 'hewan'], ['mawar', 'bunga', 'tumbuhan'], ['sedan', 'mobil', 'kendaraan'], ['pensil', 'alat tulis', 'benda'],
            ['apel', 'buah', 'makanan'], ['helikopter', 'pesawat', 'kendaraan udara'], ['biola', 'alat musik', 'benda seni'], ['kepiting', 'krustasea', 'hewan laut'],
            ['pelari', 'atlet', 'olahragawan'], ['jeruk', 'buah', 'tanaman'], ['kakatua', 'burung', 'hewan'], ['meja', 'perabot', 'benda'],
            ['Bandung', 'kota', 'wilayah'], ['bayam', 'sayuran', 'makanan'],
        ];
    }

    /**
     * The scales of the batteries: the label, what the scale measures, and the kind (`ability` for a question with a right answer, `attitude` for a Likert scale).
     *
     * @return array<string, array{label: string, about: string, kind: string}>
     */
    public static function scales(): array
    {
        return [
            'verbal_se' => ['label' => 'Melengkapi kalimat (SE)', 'about' => 'ketepatan memahami makna dan susunan kalimat', 'kind' => 'ability'],
            'verbal_wa' => ['label' => 'Pemilihan kata (WA)', 'about' => 'ketajaman mengelompokkan dan membedakan konsep', 'kind' => 'ability'],
            'verbal_an' => ['label' => 'Analogi (AN)', 'about' => 'kemampuan menemukan hubungan antar konsep', 'kind' => 'ability'],
            'verbal_ge' => ['label' => 'Persamaan (GE)', 'about' => 'kemampuan membentuk pengertian dan menemukan ciri bersama', 'kind' => 'ability'],
            'numeric_ra' => ['label' => 'Hitungan (RA)', 'about' => 'kemampuan berhitung praktis dalam persoalan sehari-hari', 'kind' => 'ability'],
            'numeric_zr' => ['label' => 'Deret angka (ZR)', 'about' => 'kemampuan menemukan pola dan aturan pada angka', 'kind' => 'ability'],
            'abstract_lg' => ['label' => 'Penalaran logis (LG)', 'about' => 'kemampuan menarik kesimpulan yang sahih dari premis', 'kind' => 'ability'],
            'checking' => ['label' => 'Ketelitian memeriksa (CK)', 'about' => 'ketepatan membandingkan data yang mirip dengan cepat', 'kind' => 'ability'],
            'accuracy' => ['label' => 'Ketelitian kerja', 'about' => 'kecenderungan bekerja cermat dan memeriksa ulang hasil', 'kind' => 'attitude'],
            'endurance' => ['label' => 'Daya tahan terhadap tekanan', 'about' => 'kemampuan tetap tenang dan bekerja stabil saat tekanan meningkat', 'kind' => 'attitude'],
            'pace' => ['label' => 'Kecepatan kerja', 'about' => 'kecenderungan bekerja dengan tempo yang konsisten dan cepat', 'kind' => 'attitude'],
            'initiative' => ['label' => 'Inisiatif', 'about' => 'kesediaan bertindak tanpa menunggu perintah', 'kind' => 'attitude'],
            'teamwork' => ['label' => 'Kerja sama', 'about' => 'kesediaan bekerja sama dan menghargai rekan', 'kind' => 'attitude'],
            'discipline' => ['label' => 'Disiplin', 'about' => 'kepatuhan pada aturan, waktu dan komitmen', 'kind' => 'attitude'],
            'safety' => ['label' => 'Kesadaran keselamatan kerja', 'about' => 'kepedulian pada prosedur dan keselamatan di tempat kerja', 'kind' => 'attitude'],
            'decision' => ['label' => 'Pengambilan keputusan', 'about' => 'keberanian dan ketepatan mengambil keputusan dengan informasi terbatas', 'kind' => 'attitude'],
            'influence' => ['label' => 'Pengaruh dan komunikasi', 'about' => 'kemampuan meyakinkan dan menggerakkan orang lain', 'kind' => 'attitude'],
            'drive' => ['label' => 'Orientasi hasil', 'about' => 'dorongan mencapai target dan standar yang tinggi', 'kind' => 'attitude'],
            'strategic' => ['label' => 'Berpikir strategis', 'about' => 'kemampuan melihat gambaran besar dan dampak jangka panjang', 'kind' => 'attitude'],
            'develop' => ['label' => 'Pengembangan orang', 'about' => 'kepedulian membimbing dan mengembangkan anggota tim', 'kind' => 'attitude'],
            'integrity' => ['label' => 'Integritas', 'about' => 'konsistensi antara nilai, ucapan dan tindakan', 'kind' => 'attitude'],
        ];
    }

    /**
     * Four statements per attitude scale; a `true` second value is a reversed statement (agreeing lowers the score).
     *
     * @return array<string, list<array{string, bool}>>
     */
    public static function statements(): array
    {
        return [
            'accuracy' => [['Saya memeriksa ulang pekerjaan saya sebelum menyerahkannya.', false], ['Saya jarang memperhatikan detail kecil dalam pekerjaan.', true], ['Saya merasa tidak tenang bila ada angka yang belum cocok.', false], ['Kesalahan kecil biasanya tidak perlu diperbaiki.', true]],
            'endurance' => [['Saya tetap bisa bekerja tenang ketika banyak tuntutan datang bersamaan.', false], ['Tenggat yang ketat membuat saya sulit berpikir jernih.', true], ['Saya pulih dengan cepat setelah mengalami hari kerja yang berat.', false], ['Saya mudah menyerah ketika pekerjaan terasa berat.', true]],
            'pace' => [['Saya biasanya menyelesaikan tugas lebih cepat dari waktu yang diberikan.', false], ['Saya butuh waktu lama untuk memulai pekerjaan baru.', true], ['Saya menjaga tempo kerja yang sama dari pagi sampai sore.', false], ['Saya sering tertinggal dari rekan dalam menyelesaikan pekerjaan.', true]],
            'initiative' => [['Saya mengerjakan hal yang perlu dilakukan tanpa menunggu diminta.', false], ['Saya lebih nyaman menunggu instruksi daripada mengusulkan ide.', true], ['Bila menemukan masalah, saya langsung mencari cara memperbaikinya.', false], ['Saya jarang menawarkan bantuan di luar tugas saya.', true]],
            'teamwork' => [['Saya senang berbagi informasi dengan rekan satu tim.', false], ['Saya lebih suka menyelesaikan semuanya sendiri daripada melibatkan orang lain.', true], ['Saya mau menerima masukan dari rekan yang berbeda pendapat.', false], ['Keberhasilan tim kurang penting bagi saya dibandingkan hasil saya sendiri.', true]],
            'discipline' => [['Saya datang tepat waktu tanpa perlu diingatkan.', false], ['Aturan kerja boleh dilanggar jika tidak ada yang melihat.', true], ['Saya memegang janji yang saya buat kepada rekan dan atasan.', false], ['Saya sering menunda pekerjaan sampai mendekati tenggat.', true]],
            'safety' => [['Saya selalu memakai perlengkapan keselamatan yang dipersyaratkan.', false], ['Prosedur keselamatan sering memperlambat pekerjaan sehingga boleh dilewati.', true], ['Saya melaporkan kondisi berbahaya yang saya temukan.', false], ['Kecelakaan kerja kebanyakan tidak bisa dicegah.', true]],
            'decision' => [['Saya berani mengambil keputusan meskipun informasinya belum lengkap.', false], ['Saya cenderung menunda keputusan penting agar tidak disalahkan.', true], ['Saya menimbang risiko sebelum memutuskan.', false], ['Saya sulit memutuskan jika ada dua pilihan yang sama baiknya.', true]],
            'influence' => [['Saya mampu menjelaskan gagasan sehingga orang lain mudah memahaminya.', false], ['Saya enggan menyampaikan pendapat di depan banyak orang.', true], ['Orang lain biasanya mau mengikuti usulan yang saya sampaikan.', false], ['Saya sulit meyakinkan orang yang tidak sependapat dengan saya.', true]],
            'drive' => [['Saya menetapkan target yang menantang untuk diri dan tim saya.', false], ['Saya puas dengan hasil yang cukup asal tidak ada keluhan.', true], ['Saya tidak berhenti sebelum target tercapai.', false], ['Saya jarang memantau kemajuan pekerjaan terhadap target.', true]],
            'strategic' => [['Saya memikirkan dampak keputusan hari ini untuk satu atau dua tahun ke depan.', false], ['Saya lebih fokus pada urusan hari ini daripada arah jangka panjang.', true], ['Saya mengamati perubahan di pasar dan industri untuk menyiapkan langkah.', false], ['Rencana jangka panjang terlalu sering berubah sehingga tidak perlu dibuat.', true]],
            'develop' => [['Saya meluangkan waktu untuk membimbing anggota tim yang masih belajar.', false], ['Mengembangkan bawahan bukan tugas utama seorang pemimpin.', true], ['Saya memberi umpan balik yang jelas dan membangun.', false], ['Saya lebih suka mengerjakan sendiri daripada mengajari orang lain.', true]],
            'integrity' => [['Saya mengakui kesalahan saya secara terbuka.', false], ['Menyembunyikan kabar buruk dari atasan kadang dibenarkan.', true], ['Saya bersikap sama kepada semua orang, baik atau tidak ada yang mengawasi.', false], ['Saya boleh mengambil sedikit keuntungan pribadi dari fasilitas kantor.', true]],
        ];
    }

    /** @return list<string> the first names of the ordering puzzles */
    public static function names(): array
    {
        return ['Adi', 'Bayu', 'Citra', 'Dian', 'Eka', 'Fajar', 'Gita', 'Hadi', 'Indah', 'Joko', 'Kirana', 'Lukman', 'Mira', 'Naufal'];
    }

    /** @return list<string> the openings of the generated work-attitude statements, each followed by "saya" and a behaviour */
    public static function situations(): array
    {
        return [
            'Pada hari kerja yang sibuk,', 'Saat tidak ada yang mengawasi,', 'Ketika tenggat semakin dekat,', 'Saat pekerjaan terasa membosankan,', 'Setelah menerima kritik dari atasan,', 'Ketika rencana berubah mendadak,',
            'Saat bekerja bersama orang yang baru dikenal,', 'Pada awal minggu kerja,', 'Ketika pekerjaan menumpuk,', 'Saat saya merasa lelah,', 'Ketika ada tugas yang belum jelas,', 'Di akhir hari kerja,',
        ];
    }

    /**
     * Four behaviours that fit an attitude scale and four that go against it, for the generated statements ("{situation} saya {behaviour}"); the second ones are reversed.
     *
     * @return array<string, array{list<string>, list<string>}>
     */
    public static function behaviours(): array
    {
        return [
            'accuracy' => [['memeriksa ulang hasil kerja sebelum diserahkan', 'mencatat detail kecil yang penting', 'mencocokkan angka dengan sumber datanya', 'menandai bagian yang masih meragukan'], ['langsung menyerahkan hasil tanpa memeriksanya', 'melewatkan detail yang tampak sepele', 'membiarkan selisih kecil pada angka', 'menebak bila data belum jelas']],
            'endurance' => [['tetap tenang dan bekerja stabil', 'mengatur napas lalu melanjutkan pekerjaan', 'memilah hal yang paling mendesak lebih dulu', 'tetap mampu berpikir jernih'], ['mudah panik dan kehilangan arah', 'menjadi mudah tersinggung', 'ingin segera menyerah', 'sulit berkonsentrasi sama sekali']],
            'pace' => [['bekerja cepat tanpa banyak jeda', 'menjaga tempo kerja yang sama', 'langsung memulai tugas yang ada', 'menyelesaikan tugas lebih cepat dari perkiraan'], ['bekerja lebih lambat dibanding rekan', 'sering berhenti tanpa alasan jelas', 'menunda-nunda memulai pekerjaan', 'baru bergerak setelah diingatkan']],
            'initiative' => [['menawarkan diri mengambil tugas tambahan', 'mencari cara memperbaiki cara kerja', 'mengusulkan ide tanpa diminta', 'mengambil langkah pertama tanpa menunggu perintah'], ['menunggu instruksi lebih dulu', 'membiarkan masalah agar orang lain yang menangani', 'menghindari tanggung jawab tambahan', 'diam saja meski punya ide']],
            'teamwork' => [['berbagi informasi dengan rekan', 'bertanya pendapat rekan setim', 'membantu rekan yang kesulitan', 'menyampaikan perbedaan pendapat dengan sopan'], ['bekerja sendiri tanpa memberi tahu tim', 'menyimpan informasi untuk diri sendiri', 'menyalahkan rekan atas masalah', 'mengabaikan masukan rekan']],
            'discipline' => [['datang dan mulai bekerja tepat waktu', 'mengikuti prosedur yang berlaku', 'menepati janji yang sudah dibuat', 'mencatat dan menyelesaikan komitmen'], ['melanggar aturan karena merasa tidak ketahuan', 'terlambat memulai pekerjaan', 'melupakan komitmen yang sudah dibuat', 'mengambil jalan pintas yang tidak diizinkan']],
            'safety' => [['memakai perlengkapan keselamatan', 'memeriksa kondisi alat sebelum dipakai', 'melaporkan kondisi yang berbahaya', 'mengingatkan rekan yang melanggar prosedur'], ['melepas perlengkapan keselamatan demi kenyamanan', 'melewati langkah pemeriksaan agar cepat', 'membiarkan benda berbahaya tidak diamankan', 'menganggap prosedur keselamatan berlebihan']],
            'decision' => [['mengambil keputusan dengan data yang ada', 'menimbang risiko lalu memutuskan', 'menetapkan pilihan dan bertanggung jawab atasnya', 'memutuskan dengan cepat pada hal yang mendesak'], ['menunda keputusan penting', 'menyerahkan keputusan kepada orang lain', 'terus mencari informasi tambahan tanpa memutuskan', 'mengubah keputusan karena tekanan']],
            'influence' => [['menjelaskan gagasan dengan jelas dan runtut', 'meyakinkan orang dengan data dan contoh', 'mendengarkan lalu menyesuaikan cara menyampaikan', 'mengajak orang lain bergerak bersama'], ['enggan menyampaikan pendapat', 'menyampaikan hal penting dengan berbelit-belit', 'menyerah bila orang tidak sependapat', 'membiarkan orang lain mengambil alih pembicaraan']],
            'drive' => [['menetapkan target yang menantang', 'memantau kemajuan terhadap target', 'tidak berhenti sebelum target tercapai', 'mencari cara agar hasilnya lebih baik'], ['puas dengan hasil yang sekadar cukup', 'melepaskan target yang terasa sulit', 'jarang memeriksa capaian kerja', 'menerima hasil di bawah standar']],
            'strategic' => [['memikirkan dampak jangka panjang', 'mengamati perubahan di sekitar organisasi', 'menghubungkan pekerjaan dengan tujuan besar', 'menyiapkan beberapa skenario'], ['hanya memikirkan urusan hari ini', 'mengabaikan arah organisasi', 'terpaku pada cara lama', 'menganggap rencana jangka panjang tidak berguna']],
            'develop' => [['membimbing anggota tim yang masih belajar', 'memberi umpan balik yang jelas dan membangun', 'memberi kesempatan orang lain mencoba tugas baru', 'memuji kemajuan anggota tim'], ['mengerjakan sendiri daripada mengajari orang lain', 'menahan umpan balik yang diperlukan', 'menganggap pengembangan bawahan bukan tugas saya', 'mengambil alih pekerjaan anggota tim']],
            'integrity' => [['mengakui kesalahan secara terbuka', 'menyampaikan kabar buruk apa adanya', 'bersikap sama kepada semua orang', 'menolak keuntungan pribadi yang tidak pantas'], ['menyembunyikan kesalahan dari atasan', 'melebih-lebihkan hasil kerja', 'memberi perlakuan istimewa kepada orang dekat', 'memakai fasilitas kantor untuk kepentingan pribadi']],
        ];
    }
}
