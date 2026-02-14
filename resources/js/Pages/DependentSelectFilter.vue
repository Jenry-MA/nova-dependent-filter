<template>
  <FilterContainer>
    <span>{{ filter.name }}</span>

    <template #filter>
      <!-- Loading state -->
      <div v-if="loading" class="py-1">
        <div class="flex items-center justify-center">
          <svg
            class="animate-spin h-4 w-4 text-gray-400"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
            ></circle>
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
            ></path>
          </svg>
        </div>
      </div>

      <!-- Searchable mode -->
      <SearchInput
        v-else-if="isSearchable"
        ref="searchableRef"
        v-model="value"
        @input="performSearch"
        @clear="clearSelection"
        @shown="() => Nova.$emit('filter-active', props.filterKey)"
        :options="availableOptions"
        :clearable="true"
        trackBy="value"
        mode="modal"
        class="w-full"
        :dusk="`${filter.uniqueKey}-search-input`"
      >
        <div v-if="selectedOption" class="flex items-center">
          {{ selectedOption.label }}
        </div>

        <template #option="{ selected, option }">
          <div class="flex items-center">
            <div class="flex-auto">
              <div
                class="text-sm font-semibold leading-normal"
                :class="{ 'text-white dark:text-gray-900': selected }"
              >
                {{ option.label }}
              </div>
            </div>
          </div>
        </template>
      </SearchInput>

      <!-- Standard select mode -->
      <SelectControl
        v-else-if="currentOptions.length > 0"
        v-model="value"
        :options="currentOptions"
        size="sm"
        label="label"
        class="w-full block"
        :dusk="filter.uniqueKey"
      >
        <option value="" :selected="!filledValue">{{ __('&mdash;') }}</option>
      </SelectControl>

      <!-- No options available -->
      <SelectControl
        v-else
        :options="[]"
        size="sm"
        class="w-full block"
        disabled
      >
        <option value="">{{ __('&mdash;') }}</option>
      </SelectControl>
    </template>
  </FilterContainer>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useStore } from 'vuex'
import debounce from 'lodash/debounce'

const props = defineProps({
  resourceName: { type: String, required: true },
  filterKey: { type: String, required: true },
  lens: String,
})

const emit = defineEmits(['change'])

const store = useStore()

const value = ref(null)
const availableOptions = ref([])
const fetchedOptions = ref(null)
const loading = ref(false)
const searchableRef = ref(null)

// --- Computed ---

const filter = computed(() =>
  store.getters[`${props.resourceName}/getFilter`](props.filterKey)
)

const currentOptions = computed(() =>
  fetchedOptions.value !== null ? fetchedOptions.value : filter.value.options
)

const isSearchable = computed(() => filter.value.searchable)

const dependencyValues = computed(() => {
  const deps = {}
  const dependsOn = filter.value.dependsOn || {}

  for (const [key, parentKey] of Object.entries(dependsOn)) {
    const parent = store.getters[`${props.resourceName}/getFilter`](parentKey)
    deps[key] = parent ? parent.currentValue : ''
  }

  return deps
})

const selectedOption = computed(() =>
  currentOptions.value.find(
    o => value.value === o.value || (o.value != null && value.value === o.value.toString())
  )
)

const filledValue = computed(() =>
  value.value !== null && value.value !== undefined && value.value !== ''
)

// --- Methods ---

function emitFilterChange() {
  store.commit(`${props.resourceName}/updateFilterState`, {
    filterClass: props.filterKey,
    value: value.value ?? '',
  })
  emit('change')
}

async function fetchDependentOptions(deps) {
  loading.value = true

  try {
    const { data } = await Nova.request().get(
      '/nova-vendor/dependent-filter/dependent-filter-options',
      {
        params: {
          resource: props.resourceName,
          filter: props.filterKey,
          ...deps,
        },
      }
    )

    fetchedOptions.value = data
    availableOptions.value = []
  } catch (error) {
    console.error('Failed to fetch dependent filter options:', error)
    fetchedOptions.value = null
  } finally {
    loading.value = false
  }
}

function clearSelection() {
  value.value = null
  availableOptions.value = []
}

function performSearch(search) {
  const trimmed = search.trim()
  if (trimmed === '') return

  availableOptions.value = currentOptions.value.filter(option =>
    option.label?.toString().toLowerCase().includes(trimmed.toLowerCase())
  )
}

// --- Debounced helpers ---

const debouncedEmit = debounce(() => emitFilterChange(), 500)
const debouncedFetch = debounce(deps => fetchDependentOptions(deps), 300)

// --- Watchers ---

watch(value, () => debouncedEmit())

watch(dependencyValues, (newDeps, oldDeps) => {
  if (JSON.stringify(newDeps) === JSON.stringify(oldDeps)) return

  value.value = null
  emitFilterChange()

  const allEmpty = Object.values(newDeps).every(
    v => v === null || v === undefined || v === ''
  )

  if (allEmpty) {
    fetchedOptions.value = null
  } else {
    debouncedFetch(newDeps)
  }
}, { deep: true })

// --- Lifecycle ---

// If this filter has dependencies, always start clean on page load
// to avoid stale values restored from the URL query string.
const hasDependencies = Object.keys(filter.value.dependsOn || {}).length > 0

if (hasDependencies && filter.value.currentValue) {
  store.commit(`${props.resourceName}/updateFilterState`, {
    filterClass: props.filterKey,
    value: '',
  })
} else if (filter.value.currentValue) {
  value.value = filter.value.currentValue
}

function handleFilterReset() {
  fetchedOptions.value = null

  if (filter.value.currentValue != '') {
    value.value = filter.value.currentValue
    return
  }

  clearSelection()
  searchableRef.value?.close()

  if (filter.value.currentValue) {
    value.value = filter.value.currentValue
  }
}

function handleClosingInactiveSearchInputs(key) {
  if (key !== props.filterKey) {
    searchableRef.value?.close()
  }
}

Nova.$on('filter-active', handleClosingInactiveSearchInputs)

onMounted(() => {
  Nova.$on('filter-reset', handleFilterReset)
})

onBeforeUnmount(() => {
  Nova.$off('filter-active', handleClosingInactiveSearchInputs)
  Nova.$off('filter-reset', handleFilterReset)
})
</script>
