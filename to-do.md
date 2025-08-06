# To-Do

### rexql Addon Development Tasks

1. (prio 2) implement missing translations and check existing translations for consistency
2. (prio 2) implement stats for webhooks
3. (prio 3) refactor resolvers so that resolvers can be used within each other
   - resolver logic should be reusable and moved away from the resolverBase
   - e.g. `rexql:route` resolver should be able to use `rexql:article` resolver
   - this will result in less code, more maintainable code, less duplication and more flexibility when extending the addon/resolverBase
