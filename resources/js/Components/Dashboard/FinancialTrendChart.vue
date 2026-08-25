<script setup lang="ts">
import { computed } from 'vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { BarChart, LineChart } from 'echarts/charts'
import { AriaComponent, GridComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

use([CanvasRenderer, BarChart, LineChart, GridComponent, TooltipComponent, AriaComponent])

interface FinancialDay {
    day: string
    date: string
    production: number
    collected: number
}

const props = defineProps<{
    data: FinancialDay[]
    currency: string
}>()

function formatMoney(amount: number): string {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: props.currency || 'USD',
        minimumFractionDigits: 2,
    }).format(amount || 0)
}

const option = computed(() => ({
    animationDuration: 700,
    aria: {
        enabled: true,
        description: 'Comparison of production and collections during the last seven days.',
    },
    color: ['#005C55', '#10B981'],
    grid: {
        top: 24,
        right: 12,
        bottom: 24,
        left: 12,
        containLabel: true,
    },
    tooltip: {
        trigger: 'axis',
        backgroundColor: '#131B2E',
        borderWidth: 0,
        textStyle: { color: '#FFFFFF' },
        valueFormatter: (value: number | string) => formatMoney(Number(value)),
    },
    xAxis: {
        type: 'category',
        data: props.data.map((day) => day.day),
        boundaryGap: true,
        axisLine: { lineStyle: { color: '#E2E8F0' } },
        axisTick: { show: false },
        axisLabel: { color: '#505F76', fontSize: 10, fontWeight: 600 },
    },
    yAxis: {
        type: 'value',
        show: false,
        splitLine: { lineStyle: { color: '#F1F5F9' } },
    },
    series: [
        {
            name: 'Produccion',
            type: 'bar',
            data: props.data.map((day) => day.production),
            barMaxWidth: 24,
            itemStyle: { borderRadius: [6, 6, 0, 0] },
            emphasis: { focus: 'series' },
        },
        {
            name: 'Cobrado',
            type: 'line',
            data: props.data.map((day) => day.collected),
            smooth: true,
            symbol: 'circle',
            symbolSize: 7,
            lineStyle: { width: 3 },
            areaStyle: { color: 'rgba(16, 185, 129, 0.12)' },
            emphasis: { focus: 'series' },
        },
    ],
}))
</script>

<template>
    <div class="h-64 w-full border-t border-[#E2E8F0] pt-3">
        <VChart class="h-full w-full" :option="option" :autoresize="{ throttle: 150 }" />
    </div>
</template>
