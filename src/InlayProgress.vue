<template>
  <div class="ifg-progress"
       :style="style"
    ></div>
</template>
<script>
export default {
  data() {
    return {
      doneBefore:0,
      percentDoneAtEndOfJob: 100,
      expectedTime: null,
      start: null,
      running: false,
      percent: null,
    }
  },
  props: {
    color: {
      default: '#46a'
    }
  },
  mounted() {
    console.log('progress colour' , this.color);
  },
  methods: {
    startTimer(expectedTime, percentDoneAtEndOfJob, reset) {
      expectedTime = expectedTime * 1000;
      if (reset) {
        this.doneBefore=0;
        this.percentDoneAtEndOfJob = percentDoneAtEndOfJob;
        this.expectedTime= expectedTime;
        this.percent= 0;
        this.start= null;
        this.running = false;
      }
      else {
        // Adding a job.
        this.doneBefore = this.percent;
        this.start = null;
        this.expectedTime = expectedTime;
        this.percentDoneAtEndOfJob = percentDoneAtEndOfJob;
      }
      if (!this.running) {
        // Start animation.
        this.running = true;
        window.requestAnimationFrame(this.animateTimer.bind(this))
      }
    },
    cancelTimer() {
      this.start = null;
      this.running = false;
    },
    animateTimer(t) {
      if (!this.start) {
        this.start = t;
      }
      const linear = Math.min(1, (t - this.start) / this.expectedTime);
      const easeout = 1 - (1-linear) * (1-linear) * (1-linear);
      this.percent = this.doneBefore + easeout * (this.percentDoneAtEndOfJob - this.doneBefore);

      if (this.running) {
        if (linear < 1) {
          // We still have stuff to animate.
          window.requestAnimationFrame(this.animateTimer.bind(this));
        }
      }
      else {
        this.running = false;
      }
    },
  },
  computed: {
    style() {
      return {
        backgroundColor: (this.running ? this.color : 'transparent'),
        width: this.percent + '%'
      };
    }
  }
}
</script>
<style lang="scss">
  .ifg-progress {
    height: 2px;
  }
</style>
