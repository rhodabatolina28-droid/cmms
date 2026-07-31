// Shared state for inventory modules
export const state = {
  allAssets: [],
  assetLookup: {},
  allUsers: [],
  currentInventoryImportToken: null,
  currentPage: 1,
  lastPage: 1,
  perPage: 50,
  filterChangeTimer: null
};
