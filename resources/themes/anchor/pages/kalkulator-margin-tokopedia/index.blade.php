<?php
    use function Laravel\Folio\{middleware, name};
    use Livewire\Volt\Component;

    use Filament\Forms\Components\RichEditor;
    use Filament\Forms\Components\TextInput;
    use Filament\Forms\Form;
    use Illuminate\Support\Facades\Storage;

    middleware('auth');
    name('kalkulator-margin-tokopedia');

    new class extends Component
    {
        public $kalkulator_margin;

        public function mount()
        {
        }
    }
?>

<x-layouts.app>
    @volt('kalkulator-margin-tokopedia')
        <x-app.container>
            <div class="flex items-center justify-between mb-4">
                <x-app.heading
                        title="Kalkulator Margin Tokopedia"
                        description="Kalkulator untuk menghitung berapa margin keuntungan dari produk yang akan Anda jual di Marketplace Tokopedia."
                        :border="true"
                    />
            </div>
            <div>
                <h2>Fitur ini akan segera hadir...</h2>
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>