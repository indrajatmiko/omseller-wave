<?php
    // use App\Models\Project;
    use Filament\Forms\Concerns\InteractsWithForms;
    use Filament\Forms\Contracts\HasForms;
    use Filament\Tables;
    use Filament\Tables\Columns\TextColumn;
    // use Filament\Tables\Table;
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};

    middleware('auth');
    name('datacenter-organik-iklan');

    new class extends Component
    {
        // use InteractsWithForms, Tables\Concerns\InteractsWithTable;

        // public ?array $data = [];

        // public function table(Table $table): Table
        // {
        //     return $table
        //         // ->query(Project::query()->where('user_id', auth()->id()))
        //         ->columns([
        //             TextColumn::make('name')
        //                 ->searchable()
        //                 ->sortable(),
        //             TextColumn::make('description')
        //                 ->limit(50)
        //                 ->searchable(),
        //             TextColumn::make('start_date')
        //                 ->date()
        //                 ->sortable(),
        //             TextColumn::make('end_date')
        //                 ->date()
        //                 ->sortable(),
        //             TextColumn::make('created_at')
        //                 ->dateTime()
        //                 ->sortable()
        //                 ->toggleable(isToggledHiddenByDefault: true),
        //         ])
        //         ->defaultSort('created_at', 'desc');
        // }
    }
?>

<x-layouts.app>
    @volt('datacenter-organik-iklan')
        <x-app.container>
            <div class="flex items-center justify-between mb-5">
                <x-app.heading title="Data Organik vs Iklan" description="Perbandingan data " :border="false" />
                <x-button tag="a" href="/datacenter-organik-iklan/step1">Upload Data</x-button>
            </div>
            <div class="overflow-x-auto border rounded-lg">
                {{-- {{ $this->table }} --}}
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>
