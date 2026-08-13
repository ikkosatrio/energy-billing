/**
 * selectSearch — dropdown dengan pencarian, dipakai komponen <x-select-search>.
 *
 * Dibangun di atas Alpine yang sudah dibundel Livewire 3, jadi tidak menambah
 * dependensi.
 *
 * Nilai terpilih disimpan sebagai state Alpine (`value`) supaya perubahannya
 * langsung terlihat di layar. Sinkronisasi dua arah dengan properti Livewire:
 *   - klik opsi  -> $wire.set(), state lokal ikut diperbarui
 *   - server ubah -> $wire.$watch() menyalin nilainya kembali ke state lokal
 *
 * Membaca langsung lewat $wire.get() di dalam getter TIDAK cukup: Alpine tidak
 * melacaknya sebagai dependensi reaktif, sehingga label tidak ikut berubah pada
 * wire:model tanpa modifier .live (yang tidak memicu render ulang dari server).
 */
document.addEventListener('alpine:init', function () {
  Alpine.data('selectSearch', function (config) {
    return {
      options: config.options || [],
      prop: config.prop,
      live: config.live !== false,
      placeholder: config.placeholder || '— pilih —',

      value: '',
      open: false,
      search: '',
      highlighted: 0,

      init: function () {
        this.value = this.normalize(this.$wire.get(this.prop));

        // Menangkap perubahan yang datang dari server: reset form, tombol
        // Batal, atau pemuatan data saat tombol Ubah ditekan.
        this.$wire.$watch(this.prop, function (incoming) {
          this.value = this.normalize(incoming);
        }.bind(this));
      },

      normalize: function (v) {
        return v === null || v === undefined ? '' : String(v);
      },

      get selected() {
        var v = this.value;
        return this.options.find(function (o) { return String(o.value) === v; }) || null;
      },

      get label() {
        return this.selected ? this.selected.label : this.placeholder;
      },

      get filtered() {
        if (!this.search) return this.options;

        var q = this.search.toLowerCase();
        // Label dan sub-label ikut dicari, sehingga pelanggan bisa ditemukan
        // lewat kodenya dan meter lewat lokasinya.
        return this.options.filter(function (o) {
          return (o.label || '').toLowerCase().indexOf(q) > -1
            || (o.sub || '').toLowerCase().indexOf(q) > -1;
        });
      },

      toggle: function () {
        this.open ? this.close() : this.openMenu();
      },

      openMenu: function () {
        this.open = true;
        this.search = '';

        // Sorot opsi yang sedang terpilih supaya panah atas/bawah bergerak
        // dari posisi sekarang, bukan dari awal daftar.
        var v = this.value;
        var idx = this.options.findIndex(function (o) { return String(o.value) === v; });
        this.highlighted = idx > -1 ? idx : 0;

        this.$nextTick(function () {
          var input = this.$refs.search;
          if (input) input.focus();
          this.scrollToHighlighted();
        }.bind(this));
      },

      close: function () {
        this.open = false;
        this.search = '';
      },

      choose: function (option) {
        // State lokal diperbarui lebih dulu agar label langsung berubah,
        // tanpa menunggu balasan server.
        this.value = this.normalize(option.value);

        // Argumen ketiga menentukan apakah perubahan langsung dikirim ke
        // server; mengikuti ada tidaknya modifier .live pada wire:model.
        this.$wire.set(this.prop, option.value, this.live);

        this.close();
        this.$refs.trigger.focus();
      },

      move: function (step) {
        if (!this.open) { this.openMenu(); return; }

        var max = this.filtered.length - 1;
        if (max < 0) return;

        this.highlighted += step;
        if (this.highlighted < 0) this.highlighted = max;
        if (this.highlighted > max) this.highlighted = 0;

        this.scrollToHighlighted();
      },

      enter: function () {
        if (!this.open) { this.openMenu(); return; }

        var option = this.filtered[this.highlighted];
        if (option) this.choose(option);
      },

      scrollToHighlighted: function () {
        var list = this.$refs.list;
        if (!list) return;

        var item = list.children[this.highlighted];
        if (!item) return;

        if (item.offsetTop < list.scrollTop) {
          list.scrollTop = item.offsetTop;
        } else if (item.offsetTop + item.offsetHeight > list.scrollTop + list.clientHeight) {
          list.scrollTop = item.offsetTop + item.offsetHeight - list.clientHeight;
        }
      },

      isSelected: function (option) {
        return String(option.value) === this.value;
      },
    };
  });
});
