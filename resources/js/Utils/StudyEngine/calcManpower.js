/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  InvestaWatch — Study Engine: Step 3 — Manpower
 *  File: resources/js/Utils/StudyEngine/calcManpower.js
 * ═══════════════════════════════════════════════════════════════════════════
 */

export function calcManpower(study, manpowerData) {
  const totalMonths      = study.duration_years * 12
  const manpowerByMonth  = new Array(totalMonths).fill(0)
  const headcountByMonth = new Array(totalMonths).fill(0)
  const byDept = {
    direct_labor:    new Array(totalMonths).fill(0),
    indirect_labor:  new Array(totalMonths).fill(0),
    admin:           new Array(totalMonths).fill(0),
    sales_marketing: new Array(totalMonths).fill(0),
  }

  for (const row of (manpowerData ?? [])) {
    const base   = (Number(row.net_salary) || 0) * (1 + (Number(row.salary_taxes_pct) || 0) / 100 + (Number(row.social_insurance_pct) || 0) / 100)
    const annInc = (Number(row.annual_increase_pct) || 0) / 100
    const dept   = row.dept || 'admin'
    const dk     = dept === 'direct_labor' ? 'direct_labor'
                 : dept === 'indirect_labor' ? 'indirect_labor'
                 : dept === 'admin_management' ? 'admin'
                 : 'sales_marketing'

    for (let m = 0; m < totalMonths; m++) {
      const year  = Math.floor(m / 12)
      const gross = base * Math.pow(1 + annInc, year)
      const count = year === 0 ? (row.y1_count?.[m % 12] ?? 0)
                  : year === 1 ? (row.y2_count?.[m % 12] ?? 0)
                  : (row.annual_count?.[year - 2] ?? 0)
      const cost  = gross * count
      manpowerByMonth[m]  += cost
      headcountByMonth[m] += count
      if (byDept[dk]) byDept[dk][m] += cost
    }
  }

  return { manpowerByMonth, manpowerCashByMonth: [...manpowerByMonth], headcountByMonth, manpowerByDept: byDept }
}
