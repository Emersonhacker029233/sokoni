{{-- Global success/status banner — previously each page had to remember to
     add its own `@if (session('status'))` block, and most (contact, report,
     comment, review submission) simply never did, so a real ->with('status', ...)
     flash was set on redirect but nothing ever rendered it (tester feedback A3).
     One shared partial in the layout means every current and future redirect-
     with-flash gets a visible confirmation for free. --}}
@if (session('status'))
    <div class="border-b border-sokoni-success/20 bg-sokoni-success/10 px-16 py-12 text-center text-sm font-medium text-sokoni-success lg:px-24">
        {{ session('status') }}
    </div>
@endif
