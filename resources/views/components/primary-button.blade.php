<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-deepgreen border border-transparent rounded-xl font-bold text-sm text-cream hover:bg-teal active:brightness-95 focus:outline-none focus:ring-2 focus:ring-teal focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
