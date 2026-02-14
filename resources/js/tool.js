import DependentSelectFilter from './Pages/DependentSelectFilter.vue'

Nova.booting((app, store) => {
  app.component('dependent-select-filter', DependentSelectFilter)
})
