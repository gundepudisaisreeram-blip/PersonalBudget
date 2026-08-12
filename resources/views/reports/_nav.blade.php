<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reports.cash-flow') ? 'active' : '' }}" href="{{ route('reports.cash-flow') }}">Cash Flow</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reports.category-spending') ? 'active' : '' }}" href="{{ route('reports.category-spending') }}">Category Spending</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reports.budget') ? 'active' : '' }}" href="{{ route('reports.budget') }}">Budget</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reports.obligations') ? 'active' : '' }}" href="{{ route('reports.obligations') }}">Obligations</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reports.trends') ? 'active' : '' }}" href="{{ route('reports.trends') }}">Trends</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reports.monthly-summary') ? 'active' : '' }}" href="{{ route('reports.monthly-summary') }}">Monthly Summary</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reports.month-review') ? 'active' : '' }}" href="{{ route('reports.month-review') }}">Month Review</a></li>
</ul>
