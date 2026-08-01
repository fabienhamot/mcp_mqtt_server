<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Connexion agent MCP</x-slot>
            <x-slot name="description">
                URL à configurer dans Claude / Cursor / MCP Inspector.
            </x-slot>

            <div class="text-sm space-y-2">
                <p>
                    <span class="font-medium">Endpoint :</span>
                    <code class="rounded bg-gray-100 px-2 py-1 dark:bg-gray-800">{{ url('/mcp/led-display') }}</code>
                </p>
                <p>
                    <span class="font-medium">Auth :</span>
                    header <code class="rounded bg-gray-100 px-2 py-1 dark:bg-gray-800">Authorization: Bearer &lt;token&gt;</code>
                </p>
                <p class="text-gray-500">
                    OAuth discovery : <code>{{ url('/.well-known/oauth-authorization-server') }}</code>
                </p>
            </div>
        </x-filament::section>

        <form wire:submit="createToken" class="space-y-4">
            {{ $this->form }}

            <x-filament::button type="submit">
                Générer le token
            </x-filament::button>
        </form>

        @if ($plainTextToken)
            <x-filament::section>
                <x-slot name="heading">Token (affichage unique)</x-slot>
                <div
                    class="rounded-lg border border-warning-300 bg-warning-50 p-4 font-mono text-sm break-all dark:border-warning-600 dark:bg-warning-950"
                    x-data="{ copied: false }"
                >
                    <p class="mb-2 text-warning-800 dark:text-warning-200">Copiez ce token maintenant — il ne sera plus réaffiché.</p>
                    <code>{{ $plainTextToken }}</code>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">Tokens actifs</x-slot>
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
