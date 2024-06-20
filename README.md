# node

### Benchmarks

Count: 1,000,000

Boot, total & nodelist (function) durations are in milliseconds.

#### app r3m_io/node list -class=Account.Permission -duration

```json
{
  "count": 1000000,
  "page": 1,
  "limit": 1000,
  "#duration": {
    "boot": 210.82496643066406,
    "total": 95621.04797363281,
    "nodelist": 95410.24208068848,
    "item_per_second": 10.45794854994422,
    "item_per_second_nodelist": 10.481055054386086
  }
}

```



#### app r3m_io/node list -class=Account.Permission -duration -limit=*
```json
{
  "count": 1000000,
  "page": 1,
  "limit": "*",
  "#duration": {
    "boot": 87.6929759979248,
    "total": 100751.79505348206,
    "nodelist": 100664.12210464478,
    "item_per_second": 9925.381473046413,
    "item_per_second_nodelist": 9934.02593786549
  }  
}
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=* -ramdisk
```json
{
  "count": 1000000,
  "page": 1,
  "ramdisk": true,
  "limit": "*",
  "#duration.create.1": {
    "boot": 155.228853225708,
    "total": 104979.98785972595,
    "nodelist": 104824.76496696472,
    "item_per_second": 9525.625029945688,
    "item_per_second_nodelist": 9539.730428350093
  },
  "#duration.run.2": {
    "boot": 157.6070785522461,
    "total": 3109.5969676971436,
    "nodelist": 2952.0089626312256,
    "item_per_second": 321585.08333655994,
    "item_per_second_nodelist": 338752.3590404909
  },
  "#duration.run.3": {
    "boot": 190.86599349975586,
    "total": 2702.5699615478516,
    "nodelist": 2511.711835861206,
    "item_per_second": 370018.17315665964,
    "item_per_second_nodelist": 398134.84402247274
  }
}
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=* -ramdisk -parallel
```json
{
  "count": 1000000,
  "page": 1,
  "ramdisk": true,
  "parallel": true,
  "threads": 8,
  "limit": "*",
  "#duration.create.1": {
    "boot": 158.07819366455078,
    "total": 126074.97811317444,
    "nodelist": 125916.98479652405,
    "item_per_second": 7931.788011910852,
    "item_per_second_nodelist": 7941.7403586653
  },
  "#duration.run.2": {
    "boot": 108.76893997192383,
    "total": 1827.6739120483398,
    "nodelist": 1718.91188621521,
    "item_per_second": 547143.554114237,
    "item_per_second_nodelist": 581763.3864885606
  },
  "#duration.run.3": {
    "boot": 140.9749984741211,
    "total": 3585.839033126831,
    "nodelist": 3445.0080394744873,
    "item_per_second": 278874.76006640645,
    "item_per_second_nodelist": 290275.0845691911
  }
}
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=* -ramdisk -parallel -thread=16
```json
{
  "count": 1000000,
  "page": 1,
  "ramdisk": true,
  "parallel": true,
  "threads": 16,
  "limit": "*",
  
}
```

