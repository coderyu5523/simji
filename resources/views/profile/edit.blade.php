<x-layouts.app :title="'프로필 · simji 심지'">
    <div class="bg-cream min-h-[60vh]">
        <div class="max-w-3xl mx-auto px-4 py-12 space-y-6">
            <h1 class="text-2xl font-extrabold text-deepgreen">프로필 설정</h1>

            <div class="rounded-3xl bg-white p-6 sm:p-8 shadow-sm ring-1 ring-black/5">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 sm:p-8 shadow-sm ring-1 ring-black/5">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 sm:p-8 shadow-sm ring-1 ring-black/5">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
