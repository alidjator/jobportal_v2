<!-- Indonesian copy of docs/legal-review.md for sending to the legal adviser. The English file is the source; update this copy whenever it changes. Internal file and setting names are left out on purpose. -->

# Brief kajian hukum: data pribadi, kriteria seleksi, dan tes psikologi

Disusun untuk penasihat hukum **ONESIS ERP** (One Solutions Integrated System) dan situs pencari kerjanya, **ONESIS Career**, yang beroperasi di
Indonesia. Modul pertama ERP ini adalah rekrutmen (lowongan, lamaran, wawancara, tes psikologi, penawaran kerja), sedangkan situs pencari kerja
terbuka untuk umum. Brief ini mendata apa yang dilakukan sistem, atau yang dirancang akan dilakukan, terhadap data pribadi yang sensitif, beserta
pertanyaan yang memerlukan jawaban. Dokumen ini adalah inventaris fakta, bukan pendapat hukum. Rujukan peraturan di dalam pertanyaan hanya
petunjuk awal dan belum diverifikasi. Disusun 9 Oktober 2026, diperbarui 19 Oktober 2026.

## 0. Prioritas jawaban

Nomor pertanyaan bersifat tetap karena dirujuk oleh dokumen desain kami. "Sudah dibangun" berarti fungsinya sudah ada di sistem, tetapi dimatikan
atau dipasang pada nilai paling aman selama pertanyaannya belum terjawab. "Dirancang" berarti fungsinya sudah direncanakan dan baru dibangun
setelah ada jawaban.

| Prioritas | Pertanyaan | Alasan | Keadaan |
|---|---|---|---|
| **A. Sebelum peluncuran** | 1 sampai 7, 10 | Syarat dan ketentuan serta kebijakan privasi harus final. Filter sensitif, batas usia, dan isian data pribadi pada CV memerlukan kepastian (tetap mati atau pada nilai bawaan sampai ada jawaban). | sudah dibangun |
| **B. Menahan pembangunan fitur** | 12, 13, 15, 20, 21 | Penerimaan penawaran kerja secara daring, daftar kandidat yang dibatasi, masa simpan data pelamar per perusahaan, kandidat yang dimasukkan dari luar platform, dan pemeriksaan pra-kerja tidak dibangun sebelum ada jawaban. | dirancang |
| **C. Sebelum mitra psikologi atau psikolog internal pertama** | 11, 17, 19 | Laporan otomatis, masa simpan data mentah, dan penandatangan internal. Tes psikologi dimatikan sampai ada jawaban. | 11 dan 17 sudah dibangun (laporan otomatis mati); 19 dirancang |
| **D. Isi kebijakan, atau setelah peluncuran** | 8, 9, 14, 16, 18, 22 | Jawabannya mengubah teks kebijakan atau fitur yang datang kemudian. Sementara itu sistem berjalan dengan perilaku yang ada. | 8, 9, 14 sudah dibangun; 16 dan 22 dirancang; 18 masih rencana |

## 1. Apa yang dilakukan sistem

| Bidang | Perilaku (saat ini, atau dirancang bila disebutkan) |
|---|---|
| Data yang dikumpulkan | Pencari kerja boleh mengisi tanggal lahir, jenis kelamin, status perkawinan, agama, dan kewarganegaraan (semuanya opsional), serta foto. Data ini dapat dilihat oleh pemilik profil dan oleh perusahaan yang dilamarnya. |
| Pencarian kandidat | Anggota tim perusahaan dapat mencari pencari kerja yang sudah menyatakan bersedia ditemukan. Pencarian dapat **disaring** berdasarkan agama, kewarganegaraan, rentang usia, dan jenis kelamin. Nilainya tidak pernah ditampilkan kepada perusahaan, hanya mempersempit hasil. **Saat ini dimatikan**: pencarian yang memakai salah satu filter itu ditolak sampai kajian ini memberi kepastian. |
| Batas usia akun | Pencari kerja yang usianya, menurut tanggal lahir, di luar batas minimum (bawaan 15 tahun) dan maksimum (bawaan 60 tahun) ditolak. Kedua batas dapat diubah admin (minimum 13 sampai 30, maksimum 40 sampai 100; nilai 100 berarti tanpa batas atas). |
| Pembuat CV | Pencari kerja dapat mencetak foto, tanggal lahir, jenis kelamin, agama, status perkawinan, dan kewarganegaraan pada CV yang dibuatnya sendiri. Semuanya tidak dicetak kecuali dicentang untuk CV tersebut. CV dapat dilampirkan pada lamaran. Ekspektasi gaji, alamat jalan, dan nomor WhatsApp tidak pernah dicetak. |
| Pencocokan dan notifikasi lowongan | Rekomendasi lowongan, rekomendasi kandidat, persentase kecocokan, dan notifikasi lowongan **tidak** memakai atribut sensitif apa pun, hanya keahlian, lokasi dan cara kerja, pengalaman, pendidikan, gaji, dan jabatan. |
| Riwayat lihat profil | Setiap kali anggota tim perusahaan membuka profil atau resume kandidat, sistem mencatat perusahaan, anggota, kandidat, dan waktunya. Kandidat dapat melihat perusahaan mana yang membuka profilnya. Data kontak tetap tersembunyi sampai kandidat melamar. |
| Riwayat lihat lowongan | Direncanakan untuk dasbor: setiap kunjungan ke lowongan dicatat dengan ID akun, atau dengan hash harian dari alamat IP dan browser bagi pengunjung yang tidak masuk akun, selama 90 hari. Setelah itu yang tersisa hanya jumlah harian per lowongan. Perusahaan hanya melihat jumlah, tidak pernah melihat siapa pengunjungnya. |
| Sumber lamaran dan skor kecocokan | Setiap lamaran mencatat asal pelamar (pencarian, rekomendasi, notifikasi, tautan langsung, undangan, tautan yang dibagikan, atau dimasukkan oleh perusahaan) dan persentase kecocokan saat melamar. Keduanya hanya dipakai dalam analitik gabungan untuk platform dan admin perusahaan (kelompok di bawah 5 orang disembunyikan); tidak ada fungsi yang menampilkannya per orang. |
| Ulasan perusahaan | Pencari kerja yang pernah melamar ke suatu perusahaan boleh mengulas perusahaan itu. Admin menyetujui ulasan lebih dulu, dan **nama penulis selalu ditampilkan** pada ulasan yang terbit. Perusahaan boleh membalas dan melaporkan ulasan. Bila akun dihapus, teks ulasan yang sudah terbit dihapus dan nilai bintangnya tetap. |
| Talent pool | Anggota tim perusahaan dapat menyimpan kandidat yang bersedia ditemukan ke talent pool perusahaan, dengan kategori dan **catatan bebas hingga 500 karakter**. Catatan itu hanya dilihat oleh anggota perusahaan tersebut, tidak pernah oleh kandidat. Entri dan catatannya disembunyikan selama kandidat tidak bersedia ditemukan; setelah itu catatannya dihapus oleh proses malam (entrinya tetap). Entri dan catatan dihapus bila akun dianonimkan atau bila anggota tim menghapus entri. |
| Penghapusan akun | Penghapusan akun menganonimkan akun dan menghapus profil, resume, notifikasi lowongan, undangan, riwayat lihat, dan catatan pembuatan CV. |
| Aktivitas tim perusahaan (P10) | Admin perusahaan dapat melihat aktivitas setiap anggota tim, termasuk alamat IP dan browser saat masuk, serta satu log aktivitas seluruh perusahaan yang dapat diekspor ke CSV; disimpan 12 bulan. |
| Tes psikologi (P11, P17 sampai P19) | Psikolog berlisensi (STR dan SILP) dari lembaga mitra, atau psikolog internal milik pelanggan (dirancang), menyusun rangkaian tes dan menandatangani laporan. Perusahaan hanya dapat memakai rangkaian tes bila platform mengaktifkan tes psikologi untuk perusahaan itu. Kandidat memberi persetujuan sebelum tes dan menerima penjelasan dalam bahasa awam. Lembaga dapat diizinkan menerbitkan laporan secara otomatis dari normanya (dimatikan sampai pertanyaan 11 terjawab). |
| Penawaran kerja (P12) | Dirancang: pencari kerja menerima surat penawaran secara daring dengan mencentang persetujuan dan memasukkan kata sandi akunnya. Waktu, alamat IP, browser, dan hash surat disimpan sebagai bukti. |
| Kandidat yang dibatasi (P13) | Dirancang: perusahaan dapat menandai pencari kerja sebagai dibatasi untuk suatu periode dengan alasan tertentu. Lamarannya diberi tanda dan ia disembunyikan dari pencarian perusahaan itu, tanpa diberi tahu. |
| Unduh data pribadi (P14) | Pencari kerja dapat mengunduh datanya. Apa yang ditulis perusahaan dan psikolog tentang dirinya tidak disertakan. |
| Masa simpan data pelamar (P15) | Dirancang: lamaran yang sudah selesai dianonimkan di sisi perusahaan setelah periode yang dipilih perusahaan (6 sampai 60 bulan, bawaan 24). |
| Percakapan yang dilaporkan (P16) | Dirancang: percakapan yang dilaporkan menyimpan pesan yang dilaporkan dan hingga 10 pesan sebelumnya untuk admin platform, lalu dihapus 180 hari setelah keputusan. |
| CV dari luar platform (P20) | Dirancang: perusahaan memasukkan nama dan email seseorang, dan boleh mengunggah CV yang diterimanya lewat email, saat wawancara langsung (walk-in), atau dari rekomendasi. Orang tersebut diundang untuk mendaftar dan melamar. CV hanya dilihat tim perusahaan dan dihapus setelah 30 hari bila tidak ada tanggapan. |
| Pemeriksaan pra-kerja (P21) | Dirancang: medical check-up, pemeriksaan latar belakang, SKCK, dan pemeriksaan referensi per pelamar, masing-masing dengan persetujuan kandidat di platform. Hasil medis hanya berupa kategori (fit, fit dengan catatan, unfit). Hasil pemeriksaan hanya dapat dilihat oleh pemegang hak akses khusus, dan setiap pembacaan dicatat. Hasil medis dan berkas pemeriksaan dihapus 90 hari setelah lamaran selesai. |
| Notifikasi WhatsApp (P22) | Dirancang, setelah peluncuran: pencari kerja yang mengaktifkan dan memverifikasi nomornya dengan kode WhatsApp menerima pengingat wawancara, tes, penawaran, dan pemeriksaan lewat WhatsApp, melalui API resmi Meta atau, bila instalasi memilihnya, gateway tidak resmi. Isi pesan dan nomor tidak disimpan di log. |

## 2. Pertanyaan

1. **Diskriminasi dalam rekrutmen.** Apakah filter agama, jenis kelamin, dan usia dalam pencarian kandidat bertentangan dengan larangan diskriminasi
   dalam ketenagakerjaan (misalnya UU 13/2003 Pasal 5 dan 6, sebagaimana telah diubah)? Bila ya, apakah platform ikut bertanggung jawab karena
   menyediakan alatnya, atau hanya perusahaan yang memakainya? Apakah jawabannya berbeda untuk kewarganegaraan (aturan izin kerja dapat menjadi
   kriteria yang sah)?
2. **Golongan data pribadi.** Menurut UU 27/2022 tentang Pelindungan Data Pribadi, apakah agama dan status perkawinan termasuk data pribadi yang
   bersifat spesifik? Bila ya, apakah isian opsional dengan syarat dan ketentuan umum seperti sekarang sudah cukup, atau diperlukan persetujuan
   eksplisit tersendiri?
3. **Batas usia akun.** Apakah menolak pencari kerja di atas 60 tahun (atau batas lain yang diatur) sah bila yang dibatasi adalah akunnya, bukan
   satu lowongan? Berapa batas usia minimum yang tepat (saat ini 15 tahun), dan apakah diperlukan persetujuan orang tua atau larangan jenis
   pekerjaan tertentu bagi yang berusia di bawah 18 tahun?
4. **Data pribadi pada CV.** Bolehkah platform menyediakan isian tersebut pada CV yang dibuat atas pilihan pencari kerja sendiri, mengingat CV di
   Indonesia lazim memuatnya? Perlukah platform menambahkan peringatan, atau menyembunyikan sebagian isian?
5. **Atribut yang dilindungi sebagai hasil pencarian.** Perusahaan tidak dapat melihat nilai-nilai tersebut dalam pencarian, tetapi dapat
   menyimpulkannya dengan menyaring berulang kali. Apakah hal ini menjadi masalah bila filter itu kelak diaktifkan?
6. **Syarat dan ketentuan serta kebijakan privasi.** Apa yang wajib dimuat dokumen tersebut mengenai pencarian kandidat (persetujuan untuk
   ditemukan, riwayat lihat profil, apa yang dilihat perusahaan), pembuat CV, dan pemrosesan untuk pencocokan? Apakah diperlukan perjanjian
   pemrosesan data dengan perusahaan pengguna?
7. **Catatan dan masa simpan.** Berapa lama riwayat lihat profil dan catatan pembuatan CV boleh disimpan, dan apa yang boleh tetap disimpan platform
   setelah akun dianonimkan?
8. **Ulasan perusahaan.** Siapa yang bertanggung jawab atas ulasan yang bersifat mencemarkan nama baik (penulisnya, atau platform sebagai
   penyelenggara)? Prosedur pemberitahuan dan penurunan konten seperti apa yang sebaiknya dijanjikan dalam syarat dan ketentuan, dan apakah
   menampilkan nama asli penulis sah dan bijak? Berapa lama ulasan yang ditolak atau laporan atas ulasan boleh disimpan?
9. **Catatan talent pool.** Apakah catatan internal perusahaan tentang seorang kandidat termasuk data pribadi yang boleh diminta untuk dilihat atau
   diperbaiki oleh kandidat, dan apa yang wajib dicantumkan dalam kebijakan privasi? Perlukah platform menolak catatan tentang atribut sensitif,
   atau menambahkan peringatan di samping isian catatan?
10. **Aktivitas anggota yang dilihat perusahaan (ditambahkan 13 Oktober 2026).** Admin perusahaan dapat melihat aktivitas setiap anggota tim,
    termasuk alamat IP dan browser saat masuk. Apa yang wajib dicantumkan dalam kebijakan privasi dan syarat bagi perusahaan, dan bolehkah data ini
    disimpan selama 12 bulan sesuai log audit? Ditambahkan 14 Oktober 2026: admin perusahaan juga melihat satu log aktivitas seluruh perusahaan
    (masuk akun, unduhan CV dan laporan, ekspor data) dan dapat mengekspornya ke CSV.
11. **Laporan psikologi yang terbit otomatis (ditambahkan 14 Oktober 2026).** Lembaga mitra dapat mengizinkan sistem menerbitkan laporan tes
    psikologi dari normanya pada saat kandidat menyelesaikan tes, atas nama, STR, dan SILP psikolog penanggung jawab yang memeriksanya sesudahnya
    dan dapat merevisinya. Apakah hal ini memenuhi UU 23/2022 (Pasal 26 dan 33: pengukuran psikologis oleh psikolog; Pasal 44: penjelasan kepada
    klien), atau apakah psikolog wajib memeriksa setiap laporan sebelum perusahaan melihatnya? Apakah jawabannya berbeda menurut jenis tes
    (inteligensi, kepribadian, sikap kerja)? Bolehkah laporan yang terbit otomatis memuat rekomendasi rekrutmen (disarankan, dipertimbangkan, belum
    disarankan) yang diturunkan dari aturan yang ditetapkan psikolog, dan apakah tepat bila rekomendasi itu tidak dicantumkan dalam penjelasan
    kepada kandidat? Bolehkah kelompok norma memakai usia dan pendidikan kandidat (diperlihatkan kepada psikolog, tidak pernah kepada perusahaan)?
12. **Menerima penawaran kerja secara daring (ditambahkan 14 Oktober 2026).** Pencari kerja menerima surat penawaran di platform dengan mencentang
    persetujuan dan memasukkan kata sandi akunnya. Waktu, alamat IP, browser, dan SHA-256 surat disimpan dalam dokumen bukti tersendiri. Apakah ini
    cukup sebagai tanda tangan elektronik menurut UU ITE dan PP 71/2019 untuk surat penawaran kerja, atau perlukah disediakan tanda tangan
    elektronik tersertifikasi (PSrE) atau e-meterai? Bolehkah platform tetap menyimpan bukti ini setelah data pelamar dianonimkan?
13. **Kandidat yang dibatasi (ditambahkan 14 Oktober 2026).** Perusahaan dapat menandai pencari kerja sebagai dibatasi (daftar hitam dengan alasan
    dan periode), sehingga lamarannya diberi tanda dan ia disembunyikan dari pencarian kandidat perusahaan itu, tanpa diberi tahu. Apakah hal ini sah
    menurut UU Pelindungan Data Pribadi dan aturan antidiskriminasi? Apakah orang tersebut wajib diberi tahu, atau dapat melihatnya melalui
    permintaan akses data, dan alasan apa saja yang wajib ditolak?
14. **Batas isi unduhan data pribadi (ditambahkan 14 Oktober 2026).** Pencari kerja dapat mengunduh datanya, tanpa apa yang ditulis perusahaan dan
    psikolog tentang dirinya: catatan dan penilaian tim, alasan tinjauan hiring manager, scorecard wawancara, catatan penolakan dan pengunduran diri
    dari tim, catatan talent pool, alasan pembatasan, teks laporan psikologi untuk perusahaan, dan catatan pemeriksaan psikolog. Apakah UU 27/2022
    Pasal 7 dan 13 mewajibkan sebagian dari isi tersebut disertakan (berkaitan dengan pertanyaan 9 dan 13), dan dalam bentuk apa (misalnya ringkasan,
    bukan teks lengkap)? Unduhan yang sudah dibangun (15 Oktober 2026) juga tidak menyertakan, dan pertanyaan ini mencakup: rekaman wawancara dan log
    panggilan (suara atau gambar kandidat), jawaban mentah dan skor tes (memuat kunci jawaban lembaga), balasan perusahaan atas ulasan orang
    tersebut, dan notifikasi di aplikasi.
15. **Masa simpan data pelamar per perusahaan (ditambahkan 14 Oktober 2026).** Lamaran yang sudah selesai (ditolak, mengundurkan diri, atau masih
    terbuka pada lowongan yang sudah ditutup) dianonimkan di sisi perusahaan setelah periode yang dipilih perusahaan, 6 sampai 60 bulan, bawaan 24.
    Karyawan yang diterima dan lamaran yang disimpan di talent pool dikecualikan; tanggal tahapan, kode alasan, dan jumlahnya tetap disimpan.
    Apakah 24 bulan merupakan nilai bawaan yang wajar menurut UU 27/2022 (pembatasan tujuan dan masa simpan), bolehkah perusahaan memilih
    periodenya, dan apakah diperlukan batas maksimum yang lebih pendek?
16. **Pesan percakapan yang dilaporkan dan dibaca admin platform (ditambahkan 15 Oktober 2026).** Bila pencari kerja atau perusahaan melaporkan
    sebuah percakapan, laporan menyimpan pesan yang dilaporkan dan hingga 10 pesan sebelumnya, yang dibaca admin platform untuk memutuskan;
    kutipan itu dihapus 180 hari setelah keputusan. Apakah hal ini sudah tercakup dalam syarat dan ketentuan serta pemberitahuan privasi, dan
    apakah 180 hari merupakan periode yang wajar?
17. **Data mentah tes psikologi dan penghapusan akun (ditambahkan 15 Oktober 2026).** Bila kandidat menghapus akunnya, jawaban, gambar, dan PDF
    laporan tes psikologinya saat ini ikut dihapus. Berapa lama lembaga mitra wajib menyimpan data mentah pemeriksaan menurut UU 23/2022 dan kode
    etik HIMPSI, dan apakah kewajiban itu mengesampingkan permintaan penghapusan dari kandidat (UU 27/2022)? Bila ya, data itu akan dipindahkan ke
    arsip lembaga yang terkunci, bukan dihapus.
18. **Hasil psikologi setelah diterima bekerja (ditambahkan 15 Oktober 2026).** Kandidat menyetujui tes psikologi untuk keperluan seleksi. Bila
    karyawan yang diterima dipindahkan ke data karyawan di ONESIS ERP, rencananya laporan dan hasil tes **tidak** ikut dipindahkan. Bolehkah
    pemberi kerja tetap memakai laporan seleksi untuk keperluan kepegawaian (penempatan, promosi) menurut UU 23/2022 dan UU 27/2022 (pembatasan
    tujuan), dan bila boleh, persetujuan apa dari karyawan dan masa simpan berapa yang berlaku?
19. **Psikolog internal yang menandatangani laporan pelamar pemberi kerjanya sendiri (ditambahkan 16 Oktober 2026).** Pelanggan dapat mendaftarkan
    psikolognya sendiri (karyawan, dengan STR dan SILP) sebagai penandatangan laporan psikologi bagi pelamar ke pelanggan tersebut. Laporan akan
    menyebutkan bahwa penandatangannya psikolog internal. Apakah UU 23/2022 (dan kode etik profesi) membolehkan seorang karyawan menandatangani
    laporan seleksi bagi pelamar ke pemberi kerjanya sendiri, pengaman benturan kepentingan apa yang diwajibkan, dan apakah kandidat wajib diberi
    tahu?
20. **CV yang diterima di luar platform (ditambahkan 19 Oktober 2026).** Perusahaan dapat memasukkan seseorang yang mengirim CV lewat email,
    datang ke wawancara langsung, atau direkomendasikan: tim mengisi nama dan email dan boleh mengunggah CV yang diterimanya. Orang tersebut
    menerima email untuk mendaftar dan melamar. CV hanya dilihat tim dan dihapus setelah 30 hari bila orang itu tidak menanggapi. Apa dasar
    pemrosesan yang sah menurut UU 27/2022 untuk menyimpan dan membaca CV sebelum orang tersebut menyetujui di platform (permintaan orang itu
    sendiri untuk dipertimbangkan, Pasal 20 ayat (2) huruf b?), apakah 30 hari dapat diterima, dan apakah email wajib menyebutkan sumber data
    (kewajiban pemberitahuan Pasal 31)? Untuk rekomendasi, datanya berasal dari pihak ketiga: apakah penyerahan oleh pemberi rekomendasi sudah
    cukup, atau tim hanya boleh mengirim undangan tanpa CV?
21. **Medical check-up dan pemeriksaan latar belakang (ditambahkan 19 Oktober 2026).** Sebelum menerima karyawan, perusahaan dapat mencatat
    medical check-up, pemeriksaan latar belakang, SKCK, dan pemeriksaan referensi per pelamar. Sistem hanya menyimpan kategori hasil medis (fit,
    fit dengan catatan, unfit), meminta persetujuan kandidat di platform untuk setiap pemeriksaan, memperlihatkan hasilnya hanya kepada anggota
    dengan hak akses khusus, mencatat setiap pembacaan, dan menghapus hasil medis serta berkas pemeriksaan 90 hari setelah lamaran selesai. Data
    kesehatan dan catatan kejahatan merupakan data pribadi yang bersifat spesifik (UU 27/2022 Pasal 4 ayat (2)). Apakah persetujuan dan
    minimisasi ini sudah cukup, bolehkah "unfit" dicatat atau hanya "tidak disarankan", bolehkah temuan latar belakang dan referensi disimpan
    sebagai berkas, dan bolehkah tenant internal membawa hasilnya ke data karyawan?
22. **Notifikasi WhatsApp melalui gateway tidak resmi (ditambahkan 19 Oktober 2026).** Pencari kerja yang mengaktifkannya menerima pengingat
    wawancara, tes, dan penawaran lewat WhatsApp. Sebuah instalasi dapat mengirimnya melalui WhatsApp Business API resmi (Meta) atau melalui gateway
    tidak resmi yang mengotomasi WhatsApp Web, yang melanggar ketentuan WhatsApp dan menjalankan sesinya di pihak ketiga. Menurut UU 27/2022,
    perjanjian apa yang diperlukan dengan setiap pengirim sebagai prosesor data, apakah gateway tidak resmi dapat diterima sama sekali untuk data
    kandidat, dan apa yang wajib dimuat kebijakan privasi mengenai WhatsApp (Meta sebagai prosesor, transfer ke luar wilayah Indonesia, Pasal 56)?

## 3. Yang dapat kami ubah setelah ada jawaban

- Mengaktifkan keempat filter sensitif untuk semua pengguna, atau menghapusnya dari sistem.
- Mengubah batas usia akun, atau menghapus batas atasnya.
- Menghapus sebagian dari enam isian data pribadi opsional pada CV, atau menambahkan peringatan.
- Mengubah isi syarat dan ketentuan serta kebijakan privasi (dokumen berversi yang dikelola admin).
- P10: memperpendek masa simpan log aktivitas 12 bulan, atau menyembunyikan alamat IP dan browser dari admin perusahaan.
- P11: mematikan laporan otomatis secara permanen, mengizinkannya per jenis tes, atau menghapus rekomendasi.
- P12: tetap memakai centang dan kata sandi, atau menambahkan tanda tangan elektronik tersertifikasi (PSrE) atau e-meterai; menghapus bukti saat
  data dianonimkan.
- P13: memberi tahu orang tersebut, menampilkan pembatasan dalam unduhan data, membatasi alasan pada daftar baku, atau menghapus fitur ini.
- P14: menambahkan isi (atau ringkasannya) ke unduhan data.
- P15 dan P16: mengubah nilai bawaan dan batas periode.
- P17: menyimpan data mentah di arsip lembaga yang terkunci alih-alih menghapusnya saat akun dihapus.
- P18: mempertahankan aturan bahwa laporan tidak pernah dipindahkan ke data karyawan, atau menambahkan persetujuan baru dari karyawan.
- P19: menambahkan pengaman (penandatangan kedua, pemberitahuan kepada kandidat) atau hanya mengizinkan psikolog mitra.
- P20: meniadakan unggah CV (undangan lewat email saja), memperpendek 30 hari, atau menyebutkan sumber data dalam email.
- P21: mencatat "tidak disarankan" alih-alih "unfit", melarang berkas untuk pemeriksaan latar belakang dan referensi, memperpendek 90 hari, atau
  tidak membawa hasil ke data karyawan.
- P22: meniadakan adapter tidak resmi, atau mewajibkan perjanjian pemrosesan data sebelum sebuah instalasi boleh mengaktifkannya.
