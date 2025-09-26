# Basic workflow

1. Receive API data
2. ```INSERT``` into database with ```CadStatus = 'PENDING'```
3. Attempt CAD post
4. ```UPDATE``` record with CFS number and ```CadStatus = 'POSTED'``` (or 'FAILED')
5. If failed .. initiate background process retry failed CAD posts - alert admins?

This gives us a better backup and retry mechanism that the reverse order.
