{{--
  Override view pagination milik Livewire.

  AppServiceProvider sudah memanggil Paginator::defaultView('vendor.pagination.default'),
  tapi setelan itu tidak pernah sampai ke halaman mana pun: trait WithPagination
  menimpanya di setiap boot komponen (SupportPagination::overrideDefaultPaginationViews)
  dan mengembalikannya lagi saat komponen selesai. Karena seluruh tabel
  berpaginasi di aplikasi ini hidup di dalam komponen Livewire, yang benar-benar
  terpakai selalu view bawaan Livewire — markup Tailwind yang mengandalkan
  preflight, sementara preflight dimatikan di project ini. Hasilnya paginator
  tanpa gaya: teks "Showing 1 to 50 of 2244 results" dan ikon SVG panah yang
  membesar memenuhi layar.

  Diperbaiki di sini, bukan dengan menambah method paginationView() di tiap
  komponen: cara itu perlu diingat setiap kali ada komponen berpaginasi baru,
  dan yang lupa tidak memunculkan error — hanya paginator rusak yang baru
  ketahuan saat dibuka orang.

  Nama filenya harus 'tailwind' karena itu yang dicari Livewire
  (config livewire.pagination_theme); isinya design system sendiri.
--}}
@include('vendor.pagination.default')
