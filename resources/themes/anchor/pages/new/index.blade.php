<?php
    use function Laravel\Folio\{middleware, name};
    use App\Models\Project;
    use Livewire\Volt\Component;
    middleware('auth');
    name('new');

    new class extends Component{
        public $new;

    }
?>

<x-layouts.app>
    @volt('new')
        <x-app.container>

            <div class="flex items-center justify-between mb-5">
                <x-app.heading
                        title="Projects"
                        description="Check out your projects below"
                        :border="false"
                    />
                <x-button tag="a" href="/projects/create">New Project</x-button>
            </div>
        </x-app.container>
    @endvolt
</x-layouts.app>