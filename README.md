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
  "#duration.create.1": {
    "boot": 149.08909797668457,
    "total": 124308.60614776611,
    "nodelist": 124159.5299243927,
    "item_per_second": 8044.495316851162,
    "item_per_second_nodelist": 8054.154204747334
  },
  "#duration.run.2": {
    "boot": 122.47490882873535,
    "total": 3325.2649307250977,
    "nodelist": 3202.799081802368,
    "item_per_second": 300727.91817581374,
    "item_per_second_nodelist": 312226.8910596953,
    "item_per_second_nodelist": 312226.8910596953
  },
  "#duration.run.3": {
    "boot": 133.25905799865723,
    "total": 2666.7540073394775,
    "nodelist": 2533.5049629211426,
    "item_per_second": 374987.7181201513,
    "item_per_second_nodelist": 394710.1010794925
  }
}
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=300 -parallel -thread=16 -index -where[]="'#class' start 'ac'" -strategy=left-only
```json
{
  "count": 1000000,
  "page": 1,
  "ramdisk": false,
  "parallel": true,
  "threads": 16,
  "limit": "300*16 (4800)",
  "index": true,
  "where": true,
  "#duration": {
    "boot": 116.26100540161133,
    "total": 3857.3079109191895,
    "nodelist": 3741.0638332366943,
    "item_per_second": 1244.3911948051275,
    "item_per_second_nodelist": 1283.0574975373074
  }
}
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=1 -index -where[]="'#class' start 'ac'" -strategy=left-only

```json
{
  "count": 1000000,
  "page": 1,
  "ramdisk": false,
  "limit": 1,
  "index": true,
  "where": true,
  "#duration": {
    "boot": 126.52301788330078,
    "total": 546.807050704956,
    "nodelist": 420.29285430908203,
    "item_per_second": 1.828798657059702,
    "item_per_second_nodelist": 2.3792933659171926
  }
}
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=300 -index -where[]="'#class' start 'ac'" -strategy=left-only

```json
{
  "count": 1000000,
  "page": 1,
  "ramdisk": false,
  "limit": 300,
  "index": true,
  "where": true,
  "#duration": {
    "boot": 218.86396408081055,
    "total": 1146.9480991363525,
    "nodelist": 928.0991554260254,
    "item_per_second": 261.563710010853,
    "item_per_second_nodelist": 323.24132421205684
  }
}
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=4800 -index -where[]="'#class' start 'ac'" -strategy=left-only

```json
{
  "count": 1000000,
  "page": 1,
  "ramdisk": false,
  "limit": 4800,
  "index": true,
  "where": true,
  "#duration": {
    "boot": 169.65198516845703,
    "total": 3321.491003036499,
    "nodelist": 3151.85809135437,
    "item_per_second": 1445.1341266954664,
    "item_per_second_nodelist": 1522.9112037647021
  }
}
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=6250 -index -parallel -where[]="'#class' start 'ac'" -strategy=left-only

```json
{
  "count": 1000000,
  "page": 1,
  "parallel": true,
  "threads": 8,
  "ramdisk": false,
  "limit": "6250 * 8 = 50000",
  "index": true,
  "where": true,
  "#duration": {
    "boot": 204.99396324157715,
    "total": 140465.09289741516,
    "nodelist": 140260.11490821838,
    "item_per_second": 355.9603241533904,
    "item_per_second_nodelist": 356.4805292845964
  }
}
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=1 -index -parallel -where[]="'#class' start 'ac'" -strategy=left-only

```json
{
  "count": 1000000,
  "page": 1,
  "parallel": true,
  "threads": 8,
  "ramdisk": false,
  "limit": "1 * 8 = 8",
  "index": true,
  "where": true,
  "#duration": {
    "boot": 157.26113319396973,
    "total": 2279.7961235046387,
    "nodelist": 2122.6089000701904,
    "item_per_second": 3.509085710568681,
    "item_per_second_nodelist": 3.768946789837476
  }
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=8 -index -where[]="'#class' start 'ac'" -strategy=left-only

```json
{
  "count": 1000000,
  "page": 1,
  "ramdisk": false,
  "limit": 8,
  "index": true,
  "where": true,
  "#duration": {
    "boot": 139.31798934936523,
    "total": 1116.6470050811768,
    "nodelist": 977.3440361022949,
    "item_per_second": 7.164305249194149,
    "item_per_second_nodelist": 8.185449242525147
  }
}
```

##### app r3m_io/node list -class=Account.Permission -duration -limit=1 -index -where[]="'#class' start 'ac'" -strategy=left-only

```json
{
  "count": 1000000,
  "page": 1,
  "ramdisk": false,
  "limit": 1,
  "index": true,
  "where": true,
  "#duration": {
    "boot": 144.39797401428223,
    "total": 639.1000747680664,
    "nodelist": 494.71521377563477,
    "item_per_second": 1.564700176827403,
    "item_per_second_nodelist": 2.0213649634262594
  }
}
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=1500 -thread=16 -index -parallel -where[]="'#class' start 'ac'" -strategy=left-only

```json
{
  "count": 1000000,
  "page": 1,
  "ramdisk": false,
  "parallel": true,
  "threads": 16,
  "limit": "1500 * 16 = 24000",
  "index": true,
  "where": true,
  "#duration": {
    "boot": 237.58697509765625,
    "total": 26448.110103607178,
    "nodelist": 26210.533142089844,
    "item_per_second": 907.4372386527049,
    "item_per_second_nodelist": 915.6624121262117
  }
}
```

#### app r3m_io/node list -class=Account.Permission -duration -limit=1500 -thread=8 -index -parallel -where[]="'#class' start 'ac'" -strategy=left-only

```json
{
  "count": 1000000,
  "page": 1,
  "ramdisk": false,
  "parallel": true,
  "threads": 8,
  "limit": "1500 * 8 = 12000",
  "index": true,
  "where": true,
  "#duration": {
    "boot": 133.46314430236816,
    "total": 11390.576124191284,
    "nodelist": 11257.124900817871,
    "item_per_second": 1053.5024628398226,
    "item_per_second_nodelist": 1065.9915480841967
  }  
}

```
