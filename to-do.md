# To-Do

### rexql Addon Development Tasks

1. ~~add rewrite rule for endpoint, so that the API can be accessed via `/api/rexql` instead of `/index.php?rex-api-call=rexql_graphql`~~
   - ~~ensure that the API is accessible via the new endpoint~~
   - ~~update the frontend to use the new endpoint~~
2. clean up `rexql` addon, remove unused code and comments
3. update `rexql` readme with new features and usage instructions
4. implement additional resolvers for other content types like media, media categories, sprog, yform tables
   - implement an all-round resolver for `rex_yform_table` to fetch data from YForm tables
5. clean up the `rexql` addon backend user interface, remove unused and unnecessary options (simplify)
6. ~~implement code formatting for the `rexql` graph ql playground~~
7. ensure that Resolvers are optimized and do not log unnecessary data
